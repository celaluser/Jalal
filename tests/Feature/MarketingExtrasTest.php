<?php

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CampaignRecipient;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\GiftCard;
use App\Modules\Marketing\Models\PriceRule;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Marketing\Models\Review;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Marketing\Services\GiftCards;
use App\Modules\Marketing\Services\Nps;
use App\Modules\Marketing\Services\Segments;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Messaging\Services\MessagingManager;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Storefront\Services\CartPricing;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    Mail::fake();
});

/** @return array{0: Restaurant, 1: array<string, mixed>} a restaurant with a $10 pizza and a $4 cola in another category */
function gxShop(array $marketing = []): array
{
    $r = Restaurant::create(['name' => 'Growth Grill', 'slug' => 'gx'.uniqid(), 'locale' => 'en', 'menu_locales' => ['en'], 'currency_code' => 'USD', 'onboarded_at' => now(), 'timezone' => 'UTC', 'marketing_settings' => $marketing]);
    app(SubscriptionService::class)->assign($r, Plan::create(['name' => 'P', 'slug' => 'p'.uniqid(), 'interval' => 'monthly', 'price' => 10, 'currency_code' => 'USD', 'limits' => [], 'features' => []]), now()->addMonth());
    $d = app(TenantContext::class)->runAs($r, function () {
        $food = Category::create(['name' => ['en' => 'Food'], 'sort' => 1]);
        $drinks = Category::create(['name' => ['en' => 'Drinks'], 'sort' => 2]);

        return ['food' => $food, 'drinks' => $drinks,
            'pizza' => Product::create(['category_id' => $food->id, 'name' => ['en' => 'Pizza'], 'price' => 10, 'sort' => 1]),
            'cola' => Product::create(['category_id' => $drinks->id, 'name' => ['en' => 'Cola'], 'price' => 4, 'sort' => 2])];
    });

    return [$r, $d];
}

function gxIn(Restaurant $r, Closure $fn): mixed
{
    return app(TenantContext::class)->runAs($r, $fn);
}

function gxUser(Restaurant $r, string $role): User
{
    $user = User::factory()->create(['restaurant_id' => $r->id]);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($r->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(config('tenancy.platform_team_id'));

    return $user;
}

function gxCustomer(Restaurant $r, array $attrs = []): Customer
{
    return gxIn($r, fn () => Customer::create(array_merge(['name' => 'Cu '.uniqid(), 'email' => uniqid().'@example.com', 'marketing_opt_in' => true, 'opted_in_at' => now(), 'orders_count' => 1], $attrs)));
}

function gxOrder(Restaurant $r, array $d, array $over = [], string $product = 'pizza'): Order
{
    return gxIn($r, fn () => app(OrderService::class)->place($r, array_merge([
        'type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => 'Gus', 'customer_phone' => '+1 555 111 2222',
        'lines' => [['product_id' => $d[$product]->id, 'qty' => 1]],
    ], $over)));
}

function gxRule(Restaurant $r, array $attrs = []): PriceRule
{
    return gxIn($r, fn () => PriceRule::create(array_merge(['name' => 'Happy', 'percent' => 20, 'from_time' => '16:00', 'to_time' => '18:00'], $attrs)));
}

describe('sms and whatsapp campaigns', function () {
    it('reaches only guests with a phone number who agreed, inside the chosen segment', function () {
        [$r] = gxShop();
        $vip = gxCustomer($r, ['phone' => '+905321112233', 'email' => null, 'orders_count' => 20]);
        gxCustomer($r, ['phone' => '+905321112244', 'email' => null, 'orders_count' => 1]);
        gxCustomer($r, ['phone' => null]);
        gxCustomer($r, ['phone' => '+905321112255', 'marketing_opt_in' => false, 'orders_count' => 30]);

        $ids = fn (string $channel, string $segment) => gxIn($r, fn () => app(CampaignService::class)->audience(new Campaign(['channel' => $channel, 'segment' => $segment, 'min_orders' => 0]), $r)->pluck('id')->all());

        expect($ids('sms', 'all'))->toHaveCount(2)->and($ids('sms', 'vip'))->toBe([$vip->id])->and($ids('email', 'all'))->toHaveCount(1)->and($ids('whatsapp', 'new'))->toHaveCount(1);
    });

    it('sends the text through the provider with a stop link and records each recipient', function () {
        $m = app(MessagingManager::class);
        $m->save($m->find('twilio'), ['account_sid' => 'AC1', 'auth_token' => 'tok', 'from' => '+15550001111']);
        $m->choose('sms', 'twilio');
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        [$r] = gxShop();
        $c = gxCustomer($r, ['name' => 'Ada', 'phone' => '+905321112233', 'email' => null]);
        $campaign = gxIn($r, fn () => Campaign::create(['name' => 'Flash', 'subject' => 'Flash', 'channel' => 'sms', 'segment' => 'all', 'body' => 'Hi {{name}}, 20% off at {{restaurant}} today!'])->fresh());

        gxIn($r, function () use ($r, $campaign) {
            app(CampaignService::class)->start($r, $campaign);
        });

        Http::assertSent(fn ($q) => $q['To'] === '+905321112233' && str_contains($q['Body'], 'Hi Ada, 20% off at Growth Grill') && str_contains($q['Body'], '/unsubscribe/'.$c->id));
        gxIn($r, function () use ($campaign) {
            expect($campaign->fresh()->status)->toBe('sent')->and($campaign->fresh()->sent_count)->toBe(1)
                ->and(CampaignRecipient::first()->status)->toBe('sent');
        });
        Mail::assertNothingSent();
    });

    it('marks recipients failed when no provider is configured instead of crashing', function () {
        [$r] = gxShop();
        gxCustomer($r, ['phone' => '+905321112233', 'email' => null]);
        $campaign = gxIn($r, fn () => Campaign::create(['name' => 'X', 'subject' => 'X', 'channel' => 'whatsapp', 'segment' => 'all', 'body' => 'Hello'])->fresh());
        gxIn($r, fn () => app(CampaignService::class)->start($r, $campaign));
        gxIn($r, fn () => expect(CampaignRecipient::first()->status)->toBe('failed')->and($campaign->fresh()->sent_count)->toBe(0));
    });

    it('creates sms campaigns from the form without a subject and caps their length', function () {
        [$r] = gxShop();
        $manager = gxUser($r, 'manager');
        $this->actingAs($manager)->post(route('campaigns.store'), ['name' => 'Texty', 'channel' => 'sms', 'segment' => 'lapsed', 'body' => 'Short text'])->assertRedirect();
        $c = gxIn($r, fn () => Campaign::first());
        expect($c->channel)->toBe('sms')->and($c->segment)->toBe('lapsed')->and($c->subject)->toBe('Texty');
        $this->actingAs($manager)->post(route('campaigns.store'), ['name' => 'Long', 'channel' => 'sms', 'body' => str_repeat('a', 601)])->assertSessionHasErrors('body');
        $this->actingAs($manager)->post(route('campaigns.store'), ['name' => 'Bad', 'channel' => 'fax', 'body' => 'x'])->assertSessionHasErrors('channel');
    });
});

describe('segments and tiers', function () {
    it('maps order counts to bronze, silver and gold from the restaurant thresholds', function () {
        [$r] = gxShop(['tier_silver' => 3, 'tier_gold' => 8]);
        $s = app(Segments::class);
        expect($s->tier(1, $r))->toBe('bronze')->and($s->tier(3, $r))->toBe('silver')->and($s->tier(8, $r))->toBe('gold');
    });

    it('rejects gold thresholds at or below silver', function () {
        [$r] = gxShop();
        $this->actingAs(gxUser($r, 'restaurant_owner'))->put(route('marketing.settings.update'), ['loyalty_every' => 4, 'loyalty_reward_type' => 'fixed', 'loyalty_reward_value' => 5, 'loyalty_valid_days' => 30, 'campaign_daily_cap' => 100, 'tier_silver' => 10, 'tier_gold' => 10])->assertSessionHasErrors('tier_gold');
    });
});

describe('happy hour', function () {
    it('takes the percentage off the dish and its extras while the rule runs, only for its category', function () {
        [$r, $d] = gxShop();
        gxRule($r, ['category_id' => $d['drinks']->id]);
        $this->travelTo(now()->setTime(17, 0));

        $quote = gxIn($r, fn () => app(CartPricing::class)->quote($r, [['product_id' => $d['cola']->id, 'qty' => 2], ['product_id' => $d['pizza']->id, 'qty' => 1]]));
        expect($quote['lines'][0]['unit_cents'])->toBe(320)->and($quote['lines'][0]['happy_percent'])->toBe(20)->and($quote['lines'][1]['unit_cents'])->toBe(1000)->and($quote['subtotal_cents'])->toBe(1640);
    });

    it('does nothing outside the hours or days, or when the rule is off', function () {
        [$r, $d] = gxShop();
        $rule = gxRule($r, ['days' => [1]]); // Mondays only
        $price = fn () => gxIn($r, fn () => app(CartPricing::class)->quote($r, [['product_id' => $d['pizza']->id, 'qty' => 1]])['subtotal_cents']);

        $this->travelTo(now()->next('Tuesday')->setTime(17, 0));
        expect($price())->toBe(1000);
        $this->travelTo(now()->next('Monday')->setTime(19, 0));
        expect($price())->toBe(1000);
        $this->travelTo(now()->setTime(17, 0)->next('Monday')->setTime(17, 0));
        expect($price())->toBe(800);
        $rule->update(['is_active' => false]);
        expect($price())->toBe(1000);
    });

    it('supports windows past midnight and picks the best rule instead of stacking', function () {
        [$r, $d] = gxShop();
        gxRule($r, ['percent' => 10, 'from_time' => '22:00', 'to_time' => '02:00']);
        gxRule($r, ['percent' => 30, 'from_time' => '23:00', 'to_time' => '23:59']);
        $this->travelTo(now()->setTime(23, 30));
        expect(gxIn($r, fn () => app(CartPricing::class)->quote($r, [['product_id' => $d['pizza']->id, 'qty' => 1]])['subtotal_cents']))->toBe(700);
        $this->travelTo(now()->addDay()->setTime(1, 0));
        expect(gxIn($r, fn () => app(CartPricing::class)->quote($r, [['product_id' => $d['pizza']->id, 'qty' => 1]])['subtotal_cents']))->toBe(900);
    });

    it('charges the discounted price on the real order and shows a banner on the menu', function () {
        [$r, $d] = gxShop();
        gxRule($r, ['name' => 'Pizza hour']);
        $this->travelTo(now()->setTime(17, 0));
        expect(gxOrder($r, $d)->subtotal_cents)->toBe(800);
        $this->get("/r/{$r->slug}")->assertOk()->assertSee('Pizza hour');
    });

    it('is managed by marketing staff and stays inside the restaurant', function () {
        [$r, $d] = gxShop();
        [$other, $od] = gxShop();
        $manager = gxUser($r, 'manager');
        $this->actingAs($manager)->post(route('pricing.store'), ['name' => 'Weekday', 'percent' => 15, 'from_time' => '16:00', 'to_time' => '18:00', 'days' => [1, 2, 3], 'category_id' => $od['food']->id])->assertRedirect();
        $rule = gxIn($r, fn () => PriceRule::first());
        expect($rule->days)->toBe([1, 2, 3])->and($rule->category_id)->toBeNull(); // another restaurant's category is ignored
        $this->actingAs($manager)->get(route('pricing.index'))->assertOk()->assertSee('Weekday');
        $this->actingAs($manager)->post(route('pricing.store'), ['name' => 'Bad', 'percent' => 95, 'from_time' => '16:00', 'to_time' => '18:00'])->assertSessionHasErrors('percent');
        $this->actingAs(gxUser($other, 'manager'))->post(route('pricing.toggle', $rule->id))->assertNotFound();
        $this->actingAs(gxUser($r, 'waiter'))->get(route('pricing.index'))->assertForbidden();
    });
});

describe('gift cards', function () {
    it('spends down across orders and never below zero', function () {
        [$r, $d] = gxShop();
        $card = gxIn($r, fn () => app(GiftCards::class)->issue($r, 1500, 'friend@example.com', 'Enjoy!'));
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->hasTo('friend@example.com'));

        $first = gxOrder($r, $d, ['promo_code' => strtolower($card->code)]);
        expect($first->discount_cents)->toBe(1000)->and($first->total_cents)->toBe(0)->and($card->fresh()->balance_cents)->toBe(500);

        $second = gxOrder($r, $d, ['promo_code' => $card->code]);
        expect($second->discount_cents)->toBe(500)->and($second->total_cents)->toBe(500)->and($card->fresh()->balance_cents)->toBe(0);

        expect(fn () => gxOrder($r, $d, ['promo_code' => $card->code]))->toThrow(OrderException::class);
    });

    it('rejects expired, switched-off and foreign cards', function () {
        [$r, $d] = gxShop();
        [$other] = gxShop();
        $expired = gxIn($r, fn () => app(GiftCards::class)->issue($r, 1000, null, null, 1));
        $expired->forceFill(['expires_at' => now()->subDay()])->save();
        $off = gxIn($r, fn () => app(GiftCards::class)->issue($r, 1000));
        $off->update(['is_active' => false]);
        $foreign = gxIn($other, fn () => app(GiftCards::class)->issue($other, 1000));

        foreach ([$expired->code, $off->code, $foreign->code, 'GIFT-NOPE-NOPE'] as $code) {
            expect(fn () => gxOrder($r, $d, ['promo_code' => $code]))->toThrow(OrderException::class);
        }
        expect($foreign->fresh()->balance_cents)->toBe(1000);
    });

    it('is issued from the panel by marketing staff only', function () {
        [$r] = gxShop();
        $this->actingAs(gxUser($r, 'manager'))->post(route('gifts.store'), ['amount' => '25.50', 'valid_days' => 90])->assertRedirect();
        $card = gxIn($r, fn () => GiftCard::first());
        expect($card->initial_cents)->toBe(2550)->and($card->balance_cents)->toBe(2550)->and($card->expires_at)->not->toBeNull();
        $this->actingAs(gxUser($r, 'manager'))->get(route('gifts.index'))->assertOk()->assertSee($card->code);
        $this->actingAs(gxUser($r, 'waiter'))->post(route('gifts.store'), ['amount' => 10])->assertForbidden();
        $this->actingAs(gxUser($r, 'manager'))->post(route('gifts.store'), ['amount' => -5])->assertSessionHasErrors('amount');
    });
});

describe('nps survey', function () {
    it('stores the 0-10 answer with the review and computes the score', function () {
        [$r, $d] = gxShop();
        $scores = [10, 9, 8, 6, 3];

        foreach ($scores as $n) {
            $o = gxOrder($r, $d);
            $o->forceFill(['status' => OrderStatus::COMPLETED])->save();
            $this->post("/r/{$r->slug}/order/{$o->token}/review", ['rating' => 5, 'nps' => $n])->assertSessionHasNoErrors();
        }

        $summary = gxIn($r, fn () => app(Nps::class)->summary());
        expect($summary)->toMatchArray(['count' => 5, 'promoters' => 2, 'passives' => 1, 'detractors' => 2, 'score' => 0]);
        $o = gxOrder($r, $d);
        $o->forceFill(['status' => OrderStatus::COMPLETED])->save();
        $this->post("/r/{$r->slug}/order/{$o->token}/review", ['rating' => 5, 'nps' => 11])->assertSessionHasErrors('nps');
        $this->actingAs(gxUser($r, 'manager'))->get(route('reviews.index'))->assertOk()->assertSee('Net Promoter Score');
    });
});

describe('autopilot', function () {
    it('sends one personal win-back code to lapsed, consenting guests and not again within 90 days', function () {
        [$r] = gxShop(['autopilot_winback' => true, 'autopilot_days' => 30, 'autopilot_percent' => 25]);
        $lapsed = gxCustomer($r, ['last_order_at' => now()->subDays(60)]);
        gxCustomer($r, ['last_order_at' => now()->subDays(5)]);
        gxCustomer($r, ['last_order_at' => now()->subDays(90), 'marketing_opt_in' => false]);
        gxCustomer($r, ['last_order_at' => now()->subDays(90), 'unsubscribed_at' => now()]);

        $this->artisan('marketing:autopilot')->assertSuccessful();
        Mail::assertSent(TemplatedMail::class, 1);
        $promo = gxIn($r, fn () => PromoCode::where('customer_id', $lapsed->id)->first());
        expect($promo->value)->toBe(25)->and($promo->max_uses)->toBe(1)->and($promo->code)->toStartWith('MISSYOU-');

        $this->artisan('marketing:autopilot')->assertSuccessful();
        Mail::assertSent(TemplatedMail::class, 1);
        expect(gxIn($r, fn () => PromoCode::count()))->toBe(1);
    });

    it('stays silent for restaurants that did not switch it on', function () {
        [$r] = gxShop();
        gxCustomer($r, ['last_order_at' => now()->subDays(200)]);
        $this->artisan('marketing:autopilot')->assertSuccessful();
        Mail::assertNothingSent();
    });
});

describe('promo templates', function () {
    it('pre-fills the promo form from a template without saving anything', function () {
        [$r] = gxShop();
        $this->actingAs(gxUser($r, 'manager'))->get(route('promos.create', ['template' => 'weekend']))->assertOk()->assertSee('WEEKEND15')->assertSee('Weekend 15%');
        expect(gxIn($r, fn () => PromoCode::count()))->toBe(0);
        $this->actingAs(gxUser($r, 'manager'))->get(route('promos.create', ['template' => 'nonsense']))->assertOk();
    });
});

describe('pixels, link page, flyer and website button', function () {
    it('loads tracking only with ids the owner saved and refuses anything that is not an id', function () {
        [$r] = gxShop();
        $owner = gxUser($r, 'restaurant_owner');
        $base = ['loyalty_every' => 4, 'loyalty_reward_type' => 'fixed', 'loyalty_reward_value' => 5, 'loyalty_valid_days' => 30, 'campaign_daily_cap' => 100];
        $this->get("/r/{$r->slug}")->assertOk()->assertDontSee('fbevents.js', false)->assertDontSee('googletagmanager', false);

        $this->actingAs($owner)->put(route('marketing.settings.update'), $base + ['pixel_meta' => "1');alert(1)//"])->assertSessionHasErrors('pixel_meta');
        $this->actingAs($owner)->put(route('marketing.settings.update'), $base + ['pixel_ga' => '"><script>'])->assertSessionHasErrors('pixel_ga');
        $this->actingAs($owner)->put(route('marketing.settings.update'), $base + ['pixel_meta' => '1234567890', 'pixel_ga' => 'G-ABC123XYZ', 'pixel_tiktok' => 'C1ABCDEF2GH'])->assertSessionHasNoErrors();

        $this->get("/r/{$r->slug}")->assertOk()->assertSee("fbq('init','1234567890')", false)->assertSee('G-ABC123XYZ', false)->assertSee('navigator.doNotTrack', false);
        $this->get("/r/{$r->slug}?kiosk=1")->assertOk()->assertDontSee('fbevents.js', false);
    });

    it('serves a link-in-bio page with only the buttons that are filled in', function () {
        [$r] = gxShop(['link_whatsapp' => '+90 532 111 22 33', 'link_instagram' => 'https://instagram.com/growthgrill', 'review_url' => 'https://g.page/r/abc']);
        $this->get("/r/{$r->slug}/links")->assertOk()->assertSee('https://wa.me/905321112233', false)->assertSee('instagram.com/growthgrill', false)->assertSee('g.page/r/abc', false)->assertDontSee('tel:', false)->assertSee('View the menu');
    });

    it('serves the website button script for the restaurant and a printable flyer to staff only', function () {
        [$r] = gxShop();
        $js = $this->get("/r/{$r->slug}/widget.js")->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        expect($js->getContent())->toContain("/r/{$r->slug}")->and($js->getContent())->toContain('Order online');

        $this->get(route('marketing.flyer'))->assertRedirect();
        $this->actingAs(gxUser($r, 'manager'))->get(route('marketing.flyer'))->assertOk()->assertSee('<svg', false)->assertSee('Growth Grill');
        $this->actingAs(gxUser($r, 'manager'))->get(route('marketing.widget'))->assertOk()->assertSee('widget.js', false);
        $this->actingAs(gxUser($r, 'waiter'))->get(route('marketing.flyer'))->assertForbidden();
    });
});

describe('birthdays', function () {
    it('lets a guest save day and month, never the year, and rejects impossible dates', function () {
        [$r] = gxShop();
        $c = gxCustomer($r, ['email' => 'bday@example.com']);
        $session = [\App\Modules\Storefront\Http\Controllers\AccountController::sessionKey($r->id) => $c->id];

        $this->withSession($session)->put("/r/{$r->slug}/account", ['name' => 'Ada', 'birth_month' => 2, 'birth_day' => 29])->assertSessionHasNoErrors();
        expect($c->fresh()->only(['birth_month', 'birth_day']))->toBe(['birth_month' => 2, 'birth_day' => 29]);
        $this->withSession($session)->put("/r/{$r->slug}/account", ['birth_month' => 4, 'birth_day' => 31])->assertSessionHasErrors('birth_day');
        $this->withSession($session)->put("/r/{$r->slug}/account", ['birth_month' => 4])->assertSessionHasErrors('birth_day');
        $this->withSession($session)->put("/r/{$r->slug}/account", ['name' => 'Ada'])->assertSessionHasNoErrors(); // clearing both is fine
        expect($c->fresh()->birth_month)->toBeNull();
    });

    it('selects guests with a birthday this month for a campaign', function () {
        [$r] = gxShop();
        $in = gxCustomer($r, ['birth_month' => now()->month, 'birth_day' => 3]);
        gxCustomer($r, ['birth_month' => now()->addMonth()->month, 'birth_day' => 3]);
        gxCustomer($r);
        $ids = gxIn($r, fn () => app(CampaignService::class)->audience(new Campaign(['channel' => 'email', 'segment' => 'birthday', 'min_orders' => 0]), $r)->pluck('id')->all());
        expect($ids)->toBe([$in->id]);
    });

    it('sends one personal code on the birthday, once a year, only with consent', function () {
        [$r] = gxShop(['autopilot_birthday' => true, 'birthday_percent' => 20]);
        $today = gxCustomer($r, ['birth_month' => now()->month, 'birth_day' => now()->day]);
        gxCustomer($r, ['birth_month' => now()->month, 'birth_day' => now()->day, 'marketing_opt_in' => false]);
        gxCustomer($r, ['birth_month' => now()->month, 'birth_day' => now()->day, 'unsubscribed_at' => now()]);
        gxCustomer($r, ['birth_month' => now()->addMonth()->month, 'birth_day' => now()->day]);

        $this->artisan('marketing:autopilot')->assertSuccessful();
        $this->artisan('marketing:autopilot')->assertSuccessful();

        Mail::assertSent(TemplatedMail::class, 1);
        $promo = gxIn($r, fn () => PromoCode::where('customer_id', $today->id)->first());
        expect($promo->value)->toBe(20)->and($promo->code)->toStartWith('BIRTHDAY-')->and($promo->max_uses)->toBe(1)->and($today->fresh()->birthday_year)->toBe(now()->year);
    });

    it('does nothing when the restaurant has not switched it on', function () {
        [$r] = gxShop();
        gxCustomer($r, ['birth_month' => now()->month, 'birth_day' => now()->day]);
        $this->artisan('marketing:autopilot')->assertSuccessful();
        Mail::assertNothingSent();
    });
});
