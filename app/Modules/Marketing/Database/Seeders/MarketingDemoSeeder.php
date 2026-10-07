<?php

namespace App\Modules\Marketing\Database\Seeders;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Marketing\Services\CustomerService;
use App\Modules\Marketing\Services\ReviewService;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Support\OrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/** Regulars, ratings, a promo code and a campaign for the demo restaurant, made through the real order flow. */
class MarketingDemoSeeder extends Seeder
{
    /** name, e-mail, orders, says yes to marketing, ratings given to their orders */
    private const GUESTS = [
        ['Maria Rossi', 'maria.rossi@example.com', 7, true, [5, 5, 4]],
        ['Luca Bianchi', 'luca.bianchi@example.com', 5, true, [5, 4]],
        ['Sofia Conti', 'sofia.conti@example.com', 4, true, [4]],
        ['James Carter', 'james.carter@example.com', 3, false, [2]],
        ['Aylin Demir', 'aylin.demir@example.com', 3, true, [5]],
        ['Omar Haddad', 'omar.haddad@example.com', 2, false, []],
        ['Emma Wilson', 'emma.wilson@example.com', 1, true, [3]],
        ['Giulia Ferrari', 'giulia.ferrari@example.com', 1, false, []],
    ];

    public function run(): void
    {
        $restaurant = app(TenantContext::class)->get();

        if (! $restaurant || Customer::exists()) {
            return;
        }

        // Dishes that can be ordered as they are (no required choice to make).
        $products = Product::with('optionGroups')->where('is_active', true)->where('is_available', true)->get()->filter(fn ($p) => $p->optionGroups->where('is_required', true)->isEmpty())->values();

        if ($products->isEmpty()) {
            return;
        }

        $restaurant->update(['marketing_settings' => ['loyalty_enabled' => true, 'loyalty_every' => 5, 'loyalty_reward_type' => 'percent', 'loyalty_reward_value' => '10']]);
        PromoCode::create(['code' => 'WELCOME10', 'description' => 'First order, shared on Instagram', 'type' => PromoCode::PERCENT, 'value' => 10]);
        PromoCode::create(['code' => 'LUNCH5', 'description' => 'Weekday lunch', 'type' => PromoCode::FIXED, 'value' => 500, 'min_order_cents' => 2500, 'ends_at' => now()->addMonths(2)]);

        // Seeding must never send mail, so the real mailer is put back afterwards.
        $real = app('mail.manager');
        Mail::fake();

        try {
            $orders = app(OrderService::class);
            $reviews = app(ReviewService::class);
            $phone = 555100;

            foreach (self::GUESTS as $i => [$name, $email, $count, $consent, $ratings]) {
                for ($n = 0; $n < $count; $n++) {
                    $lines = $products->random(min(2, $products->count()))->map(fn ($p) => ['product_id' => $p->id, 'qty' => random_int(1, 2)])->values()->all();
                    $order = $orders->place($restaurant, [
                        'type' => 'takeaway', 'payment_method' => 'cash', 'customer_name' => $name, 'customer_email' => $email,
                        'customer_phone' => '+39 02 '.($phone + $i), 'marketing_opt_in' => $consent, 'lines' => $lines,
                    ]);

                    foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::COMPLETED] as $step) {
                        $orders->transition($order, $step);
                    }

                    // Spread the orders over the last weeks.
                    $when = now()->subDays(random_int(1, 60 - $n * 3))->subMinutes(random_int(0, 600));
                    Order::whereKey($order->id)->update(['created_at' => $when, 'completed_at' => $when->copy()->addMinutes(25)]);

                    if (isset($ratings[$n])) {
                        $review = $reviews->submit($restaurant, $order->fresh(), $ratings[$n], [1 => 'Cold food and a long wait.', 2 => 'Slow service, but the pasta was fine.', 3 => 'Good, nothing special.', 4 => 'Tasty and quick, will order again.', 5 => 'Best pizza in town! Fresh and hot.'][$ratings[$n]]);
                        $review->forceFill(['created_at' => $when->copy()->addDay()])->save();
                    }
                }
            }

            foreach (Customer::all() as $customer) {
                app(CustomerService::class)->refresh($customer);
            }
        } finally {
            app()->instance('mail.manager', $real);
            Mail::clearResolvedInstance('mail.manager');
        }

        Campaign::create(['name' => 'Autumn menu', 'subject' => 'Our autumn menu is here, {{name}}', 'body' => "Hi {{name}},\n\nThe **autumn menu** just landed at {{restaurant}}: truffle risotto, pumpkin ravioli and a new tiramisu.\n\nShow this e-mail for a free espresso with your next order."]);
    }
}
