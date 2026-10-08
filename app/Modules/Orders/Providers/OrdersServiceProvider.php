<?php

namespace App\Modules\Orders\Providers;

use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Orders\Listeners\AutoPrint;
use App\Modules\Orders\Listeners\NotifyGuest;
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
        RestaurantNav::add('orders', 'panel.nav.shifts', 'shifts.index', 'shifts.*', icon: 'wallet', can: 'payments.manage');
        RestaurantNav::add('orders', 'panel.nav.courier', 'courier.index', 'courier.*', icon: 'truck', can: 'delivery.view');
        RestaurantNav::add('settings', 'panel.nav.delivery_zones', 'delivery.zones', 'delivery.zones*', icon: 'store', can: 'delivery.manage');
        RestaurantNav::add('orders', 'panel.nav.new_order', 'orders.pos.index', icon: 'plus', can: 'orders.create');
        RestaurantNav::add('settings', 'panel.nav.online_payments', 'payments.settings', 'payments.settings*', icon: 'credit-card', can: 'payments.manage');
        RestaurantNav::add('settings', 'panel.nav.ordering', 'orders.settings', icon: 'sliders', can: 'settings.manage');

        Event::subscribe(SendOrderEmails::class);
        Event::subscribe(NotifyGuest::class);
        Event::subscribe(AutoPrint::class);
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
        EmailTemplateRegistry::register('low_stock', [
            'label' => 'Low stock (owner)', 'required' => false, 'variables' => ['restaurant', 'items', 'stock_url'],
            'sample' => ['restaurant' => 'Bella Italia', 'items' => 'Margherita (Only 3 left), Tiramisu (Out of stock)', 'stock_url' => 'https://example.com/menu/stock'],
            'subject' => 'Running low · {{restaurant}}',
            'body' => "Some dishes are almost gone at **{{restaurant}}**:\n\n{{items}}\n\n[Update your stock]({{stock_url}})",
        ]);
        EmailTemplateRegistry::register('order_unaccepted', [
            'label' => 'Order nobody picked up (owner)', 'required' => false, 'variables' => ['restaurant', 'number', 'minutes', 'order_url'],
            'sample' => ['restaurant' => 'Bella Italia', 'number' => '#1042', 'minutes' => '8', 'order_url' => 'https://example.com/orders/1'],
            'subject' => 'Order {{number}} has been waiting {{minutes}} minutes · {{restaurant}}',
            'body' => "Order **{{number}}** at **{{restaurant}}** arrived {{minutes}} minutes ago and nobody has accepted it yet.\n\n[Open the order]({{order_url}})",
        ]);
        EmailTemplateRegistry::register('order_receipt', [
            'label' => 'Receipt (guest)', 'required' => false, 'variables' => $vars, 'sample' => $sample,
            'subject' => 'Your receipt {{number}} · {{restaurant}}',
            'body' => "Hi {{name}},\n\nThank you for visiting **{{restaurant}}**. Your receipt for order **{{number}}** ({{total}}) is attached.\n\n[See it online]({{status_url}})",
        ]);
        EmailTemplateRegistry::register('order_cancelled', [
            'label' => 'Order cancelled (guest)', 'required' => false, 'variables' => $vars, 'sample' => $sample,
            'subject' => 'Your order {{number}} was cancelled · {{restaurant}}',
            'body' => "Hi {{name}},\n\nSorry, **{{restaurant}}** had to cancel your order **{{number}}**. {{reason}}\n\n[See the details]({{status_url}})",
        ]);
    }
}
