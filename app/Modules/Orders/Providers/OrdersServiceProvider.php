<?php

namespace App\Modules\Orders\Providers;

use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Orders\Listeners\SendOrderEmails;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'orders');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('orders', 'panel.nav.orders', 'orders.board', 'orders.board|orders.show', icon: 'bell', can: 'orders.view');
        RestaurantNav::add('orders', 'panel.nav.new_order', 'orders.pos.index', icon: 'plus', can: 'orders.create');
        RestaurantNav::add('settings', 'panel.nav.ordering', 'orders.settings', icon: 'sliders', can: 'settings.manage');

        Event::subscribe(SendOrderEmails::class);
        $this->registerEmailTemplates();
    }

    /** Guest e-mails, editable by the platform owner like every other template. */
    private function registerEmailTemplates(): void
    {
        $vars = ['name', 'restaurant', 'number', 'total', 'minutes', 'type', 'status_url', 'reason'];
        $sample = ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'number' => '#1042', 'total' => '$31.50', 'minutes' => '15', 'type' => 'Takeaway', 'status_url' => 'https://example.com/r/bella/order/abc', 'reason' => 'Sold out'];

        EmailTemplateRegistry::register('order_received', [
            'label' => 'Order received (guest)', 'required' => false, 'variables' => $vars, 'sample' => $sample,
            'subject' => 'We got your order {{number}} · {{restaurant}}',
            'body' => "Hi {{name}},\n\nThanks for your order at **{{restaurant}}**. Your order number is **{{number}}** ({{type}}, {{total}}). It should be ready in about {{minutes}} minutes.\n\n[Follow your order]({{status_url}})",
        ]);
        EmailTemplateRegistry::register('order_ready', [
            'label' => 'Order ready (guest)', 'required' => false, 'variables' => $vars, 'sample' => $sample,
            'subject' => 'Your order {{number}} is ready · {{restaurant}}',
            'body' => "Hi {{name}},\n\nGood news: your order **{{number}}** at **{{restaurant}}** is ready.\n\n[Open your order]({{status_url}})",
        ]);
        EmailTemplateRegistry::register('order_cancelled', [
            'label' => 'Order cancelled (guest)', 'required' => false, 'variables' => $vars, 'sample' => $sample,
            'subject' => 'Your order {{number}} was cancelled · {{restaurant}}',
            'body' => "Hi {{name}},\n\nSorry, **{{restaurant}}** had to cancel your order **{{number}}**. {{reason}}\n\n[See the details]({{status_url}})",
        ]);
    }
}
