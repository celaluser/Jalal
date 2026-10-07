<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Support\Models\Announcement;
use App\Modules\Support\Models\Ticket;
use App\Modules\Support\Models\TicketReply;
use App\Modules\Support\Services\TicketService;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['password' => 'long-enough-1']);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->admin->assignRole(Permissions::SUPER_ADMIN);
});

function staffOf(Restaurant $restaurant, string $role = Permissions::OWNER): User
{
    $user = User::factory()->create(['restaurant_id' => $restaurant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));

    if ($role === Permissions::OWNER) {
        $restaurant->update(['owner_id' => $user->id]);
    }

    return $user;
}

function shop(string $name = 'Shop'): Restaurant
{
    return Restaurant::create(['name' => $name, 'slug' => strtolower($name).uniqid()]);
}

describe('restaurant side', function () {
    it('resolves the tenant before route model binding, even on a fresh request', function () {
        Mail::fake();
        $owner = staffOf(shop());
        $ticket = app(TicketService::class)->open($owner->restaurant, $owner, 'Mine', 'body', 'low');

        // In production every request starts with an empty tenant context; tests reuse the app between
        // requests, which once hid a bug where binding ran before the tenant was known.
        app()->forgetInstance(TenantContext::class);

        $this->actingAs($owner)->get("/support/{$ticket->id}")->assertOk()->assertSee('Mine');
    });

    it('lets staff with the support permission open a ticket and see it', function () {
        Mail::fake();
        $owner = staffOf($r = shop('Alpha'));

        $this->actingAs($owner)->post('/support', ['subject' => 'Printer broken', 'message' => "Line one\nLine two", 'priority' => 'high'])->assertRedirect();

        $ticket = Ticket::allTenants()->firstOrFail();
        expect($ticket->restaurant_id)->toBe($r->id)->and($ticket->status)->toBe('open')->and($ticket->priority)->toBe('high')
            ->and($ticket->replies)->toHaveCount(1);

        $this->get('/support')->assertOk()->assertSee('Printer broken');
        $this->get("/support/{$ticket->id}")->assertOk()->assertSee('Line one');
    });

    it('keeps tickets private per restaurant: other restaurants get 404 on view, reply and close', function () {
        Mail::fake();
        $a = staffOf(shop('Alpha'));
        $b = staffOf(shop('Beta'));
        $ticket = app(TicketService::class)->open($a->restaurant, $a, 'Secret billing issue', 'Our bank details...', 'normal');

        $this->actingAs($b)->get('/support')->assertOk()->assertDontSee('Secret billing issue');
        $this->get("/support/{$ticket->id}")->assertNotFound();
        $this->post("/support/{$ticket->id}/reply", ['message' => 'hi'])->assertNotFound();
        $this->post("/support/{$ticket->id}/close")->assertNotFound();

        expect($ticket->fresh()->status)->toBe('open')->and(TicketReply::allTenants()->count())->toBe(1);
    });

    it('is closed to staff roles without the permission and to guests', function () {
        $r = shop();
        $waiter = staffOf($r, Permissions::WAITER);
        $manager = staffOf($r, Permissions::MANAGER);

        $this->get('/support')->assertRedirect(route('login'));
        $this->actingAs($waiter)->get('/support')->assertForbidden();
        $this->actingAs($waiter)->post('/support', ['subject' => 's', 'message' => 'm', 'priority' => 'low'])->assertForbidden();
        $this->actingAs($manager)->get('/support')->assertOk(); // managers have it
    });

    it('validates new tickets and replies', function () {
        $owner = staffOf(shop());
        $this->actingAs($owner);

        $this->post('/support', ['subject' => '', 'message' => '', 'priority' => 'normal'])->assertSessionHasErrors(['subject', 'message']);
        $this->post('/support', ['subject' => 's', 'message' => 'm', 'priority' => 'urgent!!'])->assertSessionHasErrors('priority');
        $this->post('/support', ['subject' => str_repeat('x', 151), 'message' => 'm', 'priority' => 'low'])->assertSessionHasErrors('subject');
        $this->post('/support', ['subject' => 's', 'message' => str_repeat('x', 5001), 'priority' => 'low'])->assertSessionHasErrors('message');
    });

    it('shows user messages as plain text, never as markup', function () {
        Mail::fake();
        $owner = staffOf(shop());
        $ticket = app(TicketService::class)->open($owner->restaurant, $owner, 'XSS', '<script>alert(1)</script> <b>bold</b>', 'low');

        $html = $this->actingAs($owner)->get("/support/{$ticket->id}")->getContent();

        expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>alert(1)</script>')->not->toContain('<b>bold</b>');
    });

    it('lets the restaurant close a ticket, and replying reopens it', function () {
        Mail::fake();
        $owner = staffOf(shop());
        $ticket = app(TicketService::class)->open($owner->restaurant, $owner, 'Q', 'body', 'low');

        app()->forgetInstance(TenantContext::class);
        $this->actingAs($owner)->post("/support/{$ticket->id}/close");
        expect($ticket->fresh())->status->toBe('closed')->closed_at->not->toBeNull();

        $this->post("/support/{$ticket->id}/reply", ['message' => 'one more thing']);
        expect($ticket->fresh())->status->toBe('open')->closed_at->toBeNull();
    });
});

describe('notifications', function () {
    it('e-mails the support address about a new ticket when one is configured', function () {
        Mail::fake();
        app(SettingsService::class)->set('general.support_email', 'help@platform.test');
        $owner = staffOf(shop('Gamma'));

        app(TicketService::class)->open($owner->restaurant, $owner, 'Need help', 'please', 'normal');

        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'ticket_new' && $m->hasTo('help@platform.test') && $m->vars['restaurant'] === 'Gamma');
    });

    it('sends nothing for a new ticket without a configured support address', function () {
        Mail::fake();
        $owner = staffOf(shop());

        app(TicketService::class)->open($owner->restaurant, $owner, 'Need help', 'please', 'normal');

        Mail::assertNothingSent();
    });

    it('e-mails the owner when staff reply, and marks the ticket answered', function () {
        Mail::fake();
        $owner = staffOf(shop());
        $ticket = app(TicketService::class)->open($owner->restaurant, $owner, 'Q', 'body', 'low');

        $this->actingAs($this->admin)->post("/admin/tickets/{$ticket->id}/reply", ['message' => 'Fixed it.'])->assertSessionHasNoErrors();

        expect($ticket->fresh()->status)->toBe('answered');
        expect(TicketReply::allTenants()->where('is_staff', true)->count())->toBe(1);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'ticket_reply' && $m->hasTo($owner->email) && $m->vars['message'] === 'Fixed it.');
    });
});

describe('admin side', function () {
    it('lists tickets of every restaurant, needing-attention first, with filters', function () {
        Mail::fake();
        $svc = app(TicketService::class);
        $a = staffOf(shop('Alpha'));
        $b = staffOf(shop('Beta'));
        $done = $svc->open($a->restaurant, $a, 'Already handled', 'x', 'low');
        $svc->close($done);
        $svc->open($b->restaurant, $b, 'Fresh problem', 'x', 'high');
        $this->actingAs($this->admin);

        $html = $this->get('/admin/tickets')->assertOk()->getContent();
        expect(strpos($html, 'Fresh problem'))->toBeLessThan(strpos($html, 'Already handled'));

        $this->get('/admin/tickets?status=closed')->assertSee('Already handled')->assertDontSee('Fresh problem');
        $this->get('/admin/tickets?priority=high')->assertSee('Fresh problem')->assertDontSee('Already handled');
        $this->get('/admin/tickets?q=Fresh')->assertSee('Fresh problem')->assertDontSee('Already handled');
        $this->get('/admin/tickets?q=%25')->assertDontSee('Fresh problem');
    });

    it('shows the thread, lets support close and reopen, and keeps staff out', function () {
        Mail::fake();
        $owner = staffOf(shop());
        $ticket = app(TicketService::class)->open($owner->restaurant, $owner, 'Thread', 'first message', 'normal');

        $this->actingAs($this->admin)->get("/admin/tickets/{$ticket->id}")->assertOk()->assertSee('first message');
        $this->post("/admin/tickets/{$ticket->id}/close");
        expect($ticket->fresh()->status)->toBe('closed');
        $this->post("/admin/tickets/{$ticket->id}/reopen");
        expect($ticket->fresh()->status)->toBe('open');
        $this->post("/admin/tickets/{$ticket->id}/reply", ['message' => ''])->assertSessionHasErrors('message');

        $this->actingAs($owner)->get('/admin/tickets')->assertForbidden();
        $this->post("/admin/tickets/{$ticket->id}/reply", ['message' => 'sneaky'])->assertForbidden();
    });

    it('404s unknown tickets', function () {
        $this->actingAs($this->admin)->get('/admin/tickets/99999')->assertNotFound();
    });
});

describe('announcements', function () {
    it('selects only active announcements inside their window', function () {
        Announcement::create(['title' => 'Live now', 'level' => 'info', 'is_active' => true]);
        Announcement::create(['title' => 'Started', 'level' => 'info', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        Announcement::create(['title' => 'Off', 'level' => 'info', 'is_active' => false]);
        Announcement::create(['title' => 'Future', 'level' => 'info', 'starts_at' => now()->addDay()]);
        Announcement::create(['title' => 'Expired', 'level' => 'info', 'ends_at' => now()->subHour()]);

        expect(Announcement::live()->pluck('title')->sort()->values()->all())->toBe(['Live now', 'Started']);
    });

    it('is shown on restaurant panel pages, with markdown made safe', function () {
        Announcement::create(['title' => 'Maintenance Sunday', 'body' => "**02:00 UTC** <script>alert(1)</script>\n\n[x](javascript:alert(2))", 'level' => 'warning']);
        $owner = staffOf(shop());

        $html = $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('Maintenance Sunday')->getContent();

        expect($html)->toContain('<strong>02:00 UTC</strong>')->not->toContain('<script>alert(1)')->not->toContain('javascript:');
        $this->get('/support')->assertSee('Maintenance Sunday');
    });

    it('offers a dismiss button only on dismissible notices', function () {
        Announcement::create(['title' => 'Sticky notice', 'level' => 'info', 'is_dismissible' => false]);
        $owner = staffOf(shop());

        $this->actingAs($owner)->get('/dashboard')->assertSee('Sticky notice')->assertDontSee(__('support.dismiss'));

        Announcement::query()->update(['is_dismissible' => true]);
        $this->get('/dashboard')->assertSee(__('support.dismiss'));
    });

    it('does not appear for ended notices', function () {
        Announcement::create(['title' => 'Old news', 'level' => 'info', 'ends_at' => now()->subDay()]);

        $this->actingAs(staffOf(shop()))->get('/dashboard')->assertDontSee('Old news');
    });

    it('is managed from the admin with validation', function () {
        $this->actingAs($this->admin);

        $this->post('/admin/announcements', ['title' => 'Hello', 'level' => 'success', 'is_active' => '1', 'starts_at' => '2030-01-01T10:00', 'ends_at' => '2030-01-02T10:00'])->assertSessionHasNoErrors();
        $a = Announcement::firstOrFail();
        expect($a->level)->toBe('success')->and($a->is_active)->toBeTrue()->and($a->is_dismissible)->toBeFalse(); // unchecked box

        $this->post('/admin/announcements', ['title' => '', 'level' => 'info'])->assertSessionHasErrors('title');
        $this->post('/admin/announcements', ['title' => 'x', 'level' => 'scary'])->assertSessionHasErrors('level');
        $this->post('/admin/announcements', ['title' => 'x', 'level' => 'info', 'starts_at' => '2030-01-02T10:00', 'ends_at' => '2030-01-01T10:00'])->assertSessionHasErrors('ends_at');

        $this->get('/admin/announcements')->assertOk()->assertSee('Hello');
        $this->get("/admin/announcements/{$a->id}/edit")->assertOk()->assertSee('value="Hello"', false);
        $this->put("/admin/announcements/{$a->id}", ['title' => 'Renamed', 'level' => 'info', 'is_active' => '1']);
        expect($a->fresh()->title)->toBe('Renamed');
        $this->delete("/admin/announcements/{$a->id}");
        expect(Announcement::count())->toBe(0);
    });

    it('is closed to restaurant staff', function () {
        $this->actingAs(staffOf(shop()))->get('/admin/announcements')->assertForbidden();
    });
});
