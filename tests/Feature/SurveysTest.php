<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Surveys\Models\Survey;
use App\Modules\Surveys\Models\SurveyResponse;
use App\Modules\Surveys\Services\SurveyResults;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
});

function svShop(): array
{
    $r = Restaurant::create(['name' => 'Survey Bistro', 'slug' => 'sv'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'timezone' => 'UTC', 'onboarded_at' => now()]);
    $plan = Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]);
    app(SubscriptionService::class)->assign($r, $plan, now()->addMonth());

    return [$r, svUser($r, Permissions::OWNER)];
}

function svUser(Restaurant $r, string $role): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function svSurvey(Restaurant $r, array $over = []): Survey
{
    return app(TenantContext::class)->runAs($r, fn () => Survey::create($over + [
        'title' => 'How was it?', 'slug' => 'how-was-it', 'is_active' => true,
        'questions' => [
            ['id' => 'qrate', 'type' => 'rating', 'label' => 'Food', 'required' => true, 'options' => []],
            ['id' => 'qnps', 'type' => 'nps', 'label' => 'Recommend?', 'required' => false, 'options' => []],
            ['id' => 'qpick', 'type' => 'choice', 'label' => 'Visit', 'required' => false, 'options' => ['Lunch', 'Dinner']],
            ['id' => 'qtext', 'type' => 'text', 'label' => 'Comment', 'required' => false, 'options' => []],
        ],
    ]));
}

it('lets the owner build, edit and delete a survey', function () {
    [$r, $owner] = svShop();

    $this->actingAs($owner)->get(route('surveys.create'))->assertOk()->assertSee(__('surveys.sample_nps'));
    $this->actingAs($owner)->post(route('surveys.store'), [
        'title' => 'Dinner feedback', 'is_active' => 1, 'after_order' => 1,
        'questions' => [
            ['id' => '', 'type' => 'rating', 'label' => 'Food', 'required' => 1],
            ['id' => '', 'type' => 'choice', 'label' => 'Which?', 'options' => "Lunch\nDinner\n\nLunch"],
        ],
    ])->assertRedirect(route('surveys.index'));

    $survey = app(TenantContext::class)->runAs($r, fn () => Survey::first());
    expect($survey->slug)->toBe('dinner-feedback')->and($survey->after_order)->toBeTrue()
        ->and($survey->questions[1]['options'])->toBe(['Lunch', 'Dinner'])->and($survey->questions[0]['id'])->toStartWith('q');

    $this->actingAs($owner)->get(route('surveys.index'))->assertOk()->assertSee('Dinner feedback')->assertSee('/survey/dinner-feedback')->assertSee(route('surveys.create'));
    $this->actingAs($owner)->put(route('surveys.update', $survey->id), ['title' => 'Renamed', 'questions' => [['id' => $survey->questions[0]['id'], 'type' => 'text', 'label' => 'Say something']]])->assertRedirect();
    expect(app(TenantContext::class)->runAs($r, fn () => Survey::find($survey->id)))->title->toBe('Renamed')->slug->toBe('dinner-feedback')->is_active->toBeFalse();

    $this->actingAs($owner)->delete(route('surveys.destroy', $survey->id))->assertRedirect(route('surveys.index'));
    expect(app(TenantContext::class)->runAs($r, fn () => Survey::count()))->toBe(0);
});

it('rejects a choice question without choices and keeps surveys to staff of that restaurant', function () {
    [$r, $owner] = svShop();
    [, $otherOwner] = svShop();

    $this->actingAs($owner)->post(route('surveys.store'), ['title' => 'T', 'questions' => [['type' => 'choice', 'label' => 'Pick', 'options' => 'Only one']]])->assertStatus(422);
    $survey = svSurvey($r);

    $this->actingAs($otherOwner)->get(route('surveys.results', $survey->id))->assertNotFound();
    $this->actingAs($otherOwner)->delete(route('surveys.destroy', $survey->id))->assertNotFound();
    $this->actingAs(svUser($r, Permissions::WAITER))->get(route('surveys.index'))->assertForbidden();
});

it('collects guest answers, validates them, and shows the results', function () {
    [$r, $owner] = svShop();
    $survey = svSurvey($r);
    $url = '/r/'.$r->slug.'/survey/'.$survey->slug;

    $this->get($url)->assertOk()->assertSee('How was it?')->assertSee('Recommend?');
    $this->post($url, ['a' => ['qnps' => 9]])->assertSessionHasErrors('a.qrate');              // required
    $this->post($url, ['a' => ['qrate' => 9]])->assertSessionHasErrors('a.qrate');             // out of range
    $this->post($url, ['a' => ['qrate' => 5, 'qpick' => 'Brunch']])->assertSessionHasErrors('a.qpick'); // not a listed choice
    expect(SurveyResponse::count())->toBe(0);

    $this->post($url, ['a' => ['qrate' => 5, 'qnps' => 10, 'qpick' => 'Dinner', 'qtext' => 'Lovely <b>soup</b>']])->assertRedirect();
    $this->post($url, ['a' => ['qrate' => 3, 'qnps' => 6, 'qpick' => 'Lunch']])->assertRedirect();
    $this->post($url, ['a' => ['qrate' => 4, 'qnps' => 9]])->assertRedirect();
    expect(SurveyResponse::count())->toBe(3)->and(Survey::find($survey->id)->responses_count)->toBe(3);

    $summary = collect(app(SurveyResults::class)->summarize(Survey::find($survey->id)))->keyBy('id');
    expect($summary['qrate']['average'])->toBe(4.0)->and($summary['qrate']['distribution'][5])->toBe(1)
        ->and($summary['qnps']['score'])->toBe(33)                                              // 2 promoters, 1 detractor of 3
        ->and($summary['qpick']['options'])->toBe(['Lunch' => 1, 'Dinner' => 1])
        ->and($summary['qtext']['latest'])->toBe(['Lovely <b>soup</b>']);

    $page = $this->actingAs($owner)->get(route('surveys.results', $survey->id))->assertOk()->assertSee('Food')->getContent();
    expect($page)->toContain('Lovely &lt;b&gt;soup&lt;/b&gt;')->not->toContain('<b>soup</b>');
});

it('exports answers as CSV without letting a cell run as a formula', function () {
    [$r, $owner] = svShop();
    $survey = svSurvey($r);
    $this->post('/r/'.$r->slug.'/survey/'.$survey->slug, ['a' => ['qrate' => 5, 'qtext' => '=HYPERLINK("http://evil.test")']])->assertRedirect();

    $csv = $this->actingAs($owner)->get(route('surveys.export', $survey->id))->assertOk()->streamedContent();
    expect($csv)->toContain('Comment')->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

it('keeps off and unknown surveys closed', function () {
    [$r] = svShop();
    svSurvey($r, ['is_active' => false]);

    $this->get('/r/'.$r->slug.'/survey/how-was-it')->assertNotFound();
    $this->get('/r/'.$r->slug.'/survey/nothing-here')->assertNotFound();
    $this->get('/r/'.$r->slug.'/survey/Bad_Slug!')->assertNotFound();
});

it('takes one answer per order and offers the survey on the order page', function () {
    [$r] = svShop();
    $survey = svSurvey($r, ['after_order' => true]);
    $product = app(TenantContext::class)->runAs($r, fn () => Product::create(['category_id' => Category::create(['name' => ['en' => 'Food'], 'sort' => 1])->id, 'name' => ['en' => 'Soup'], 'price' => 5, 'sort' => 1]));
    $order = app(TenantContext::class)->runAs($r, fn () => app(OrderService::class)->place($r, ['type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'G', 'customer_phone' => '+1 555 111 2222', 'lines' => [['product_id' => $product->id, 'qty' => 1]]]));

    $status = $this->get('/r/'.$r->slug.'/order/'.$order->token)->assertOk()->getContent();
    expect($status)->toContain('/survey/'.$survey->slug.'?o='.$order->token);

    $url = '/r/'.$r->slug.'/survey/'.$survey->slug;
    $this->post($url, ['o' => $order->token, 'a' => ['qrate' => 5]])->assertRedirect();
    $this->post($url, ['o' => $order->token, 'a' => ['qrate' => 1]])->assertRedirect();   // a second try changes nothing
    expect(SurveyResponse::count())->toBe(1)->and(SurveyResponse::first()->order_id)->toBe($order->id)->and(SurveyResponse::first()->answers['qrate'])->toBe(5);
    $this->get($url.'?o='.$order->token)->assertOk()->assertSee(__('surveys.thanks'));
});
