<?php

namespace App\Modules\Marketing\Providers;

use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Demo\Support\DemoDataRegistry;
use App\Modules\Marketing\Console\Autopilot;
use App\Modules\Marketing\Database\Seeders\MarketingDemoSeeder;
use App\Modules\Marketing\Listeners\RecordCustomerActivity;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/marketing.php'), 'marketing');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'marketing');
        Blade::anonymousComponentPath(__DIR__.'/../Resources/views/components', 'marketing');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('marketing', 'panel.nav.customers', 'customers.index', 'customers.*', icon: 'users', can: 'customers.view');
        RestaurantNav::add('marketing', 'panel.nav.reviews', 'reviews.index', 'reviews.*', icon: 'star', can: 'customers.view');
        RestaurantNav::add('marketing', 'panel.nav.promos', 'promos.index', 'promos.*', icon: 'tag', can: 'marketing.manage');
        RestaurantNav::add('marketing', 'panel.nav.banners', 'banners.index', 'banners.*', icon: 'image', can: 'marketing.manage');
        RestaurantNav::add('marketing', 'panel.nav.pricing', 'pricing.index', 'pricing.*', icon: 'tag', can: 'marketing.manage');
        RestaurantNav::add('marketing', 'panel.nav.segments', 'segments.index', 'segments.*', icon: 'users', can: 'marketing.manage');
        RestaurantNav::add('marketing', 'panel.nav.gifts', 'gifts.index', 'gifts.*', icon: 'gift', can: 'marketing.manage');
        RestaurantNav::add('marketing', 'panel.nav.campaigns', 'campaigns.index', 'campaigns.*', icon: 'mail', can: 'marketing.manage');
        RestaurantNav::add('marketing', 'panel.nav.loyalty', 'marketing.settings', icon: 'gift', can: 'marketing.manage');

        if ($this->app->runningInConsole()) {
            $this->commands([Autopilot::class]);
        }

        Event::subscribe(RecordCustomerActivity::class);
        DemoDataRegistry::register(MarketingDemoSeeder::class);
        $this->registerEmailTemplates();
    }

    private function registerEmailTemplates(): void
    {
        EmailTemplateRegistry::register('loyalty_reward', [
            'label' => 'Loyalty reward (guest)', 'required' => false,
            'variables' => ['name', 'restaurant', 'code', 'reward', 'expires', 'orders', 'menu_url'],
            'sample' => ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'code' => 'THANKS-4KQ9ZD', 'reward' => '10% off', 'expires' => 'Dec 31, 2026', 'orders' => '5', 'menu_url' => 'https://example.com/r/bella'],
            'subject' => 'A thank-you from {{restaurant}}: {{reward}}',
            'body' => "Hi {{name}},\n\nYou have ordered {{orders}} times at **{{restaurant}}**, thank you! Here is **{{reward}}** on your next order:\n\n**{{code}}**\n\nEnter it at checkout before {{expires}}. It works once and only for you.\n\n[Order now]({{menu_url}})",
        ]);
        EmailTemplateRegistry::register('winback', [
            'label' => 'We miss you (guest)', 'required' => false,
            'variables' => ['name', 'restaurant', 'code', 'reward', 'expires', 'menu_url'],
            'sample' => ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'code' => 'MISSYOU-4KQ9ZD', 'reward' => '15% off', 'expires' => 'Dec 31, 2026', 'menu_url' => 'https://example.com/r/bella'],
            'subject' => '{{restaurant}} misses you: {{reward}}',
            'body' => "Hi {{name}},\n\nIt has been a while since your last order at **{{restaurant}}**. Here is **{{reward}}** to welcome you back:\n\n**{{code}}**\n\nEnter it at checkout before {{expires}}. It works once and only for you.\n\n[Order now]({{menu_url}})",
        ]);
        EmailTemplateRegistry::register('birthday', [
            'label' => 'Birthday treat (guest)', 'required' => false,
            'variables' => ['name', 'restaurant', 'code', 'reward', 'expires', 'menu_url'],
            'sample' => ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'code' => 'BIRTHDAY-4KQ9ZD', 'reward' => '15% off', 'expires' => 'Dec 31, 2026', 'menu_url' => 'https://example.com/r/bella'],
            'subject' => 'Happy birthday from {{restaurant}}!',
            'body' => "Happy birthday, {{name}}!\n\nTo celebrate, here is **{{reward}}** at **{{restaurant}}**:\n\n**{{code}}**\n\nEnter it at checkout before {{expires}}. It works once and only for you.\n\n[Order now]({{menu_url}})",
        ]);
        EmailTemplateRegistry::register('gift_card', [
            'label' => 'Gift card (recipient)', 'required' => false,
            'variables' => ['restaurant', 'code', 'amount', 'note', 'menu_url'],
            'sample' => ['restaurant' => 'Bella Italia', 'code' => 'GIFT-AB12-CD34', 'amount' => '$50.00', 'note' => 'Happy birthday!', 'menu_url' => 'https://example.com/r/bella'],
            'subject' => 'A gift card for {{restaurant}}: {{amount}}',
            'body' => "You received a gift card worth **{{amount}}** for **{{restaurant}}**.\n\n{{note}}\n\nYour code: **{{code}}**\n\nEnter it at checkout; any balance stays on the card.\n\n[Order now]({{menu_url}})",
        ]);
        EmailTemplateRegistry::register('review_request', [
            'label' => 'Review request (guest)', 'required' => false,
            'variables' => ['name', 'restaurant', 'number', 'review_url'],
            'sample' => ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'number' => '#1042', 'review_url' => 'https://example.com/r/bella/order/abc'],
            'subject' => 'How was your order from {{restaurant}}?',
            'body' => "Hi {{name}},\n\nWe hope you enjoyed order **{{number}}** from **{{restaurant}}**. Would you rate it? It takes ten seconds and helps us a lot.\n\n[Rate my order]({{review_url}})",
        ]);
    }
}
