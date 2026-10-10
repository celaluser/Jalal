<?php

namespace App\Modules\Reservations\Providers;

use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Reservations\Console\ExpireDeposits;
use App\Modules\Reservations\Console\SendReminders;
use Illuminate\Support\ServiceProvider;

/** Table bookings: a guest page with live free times, a staff day view, e-mails and reminders. */
class ReservationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'reservations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->commands([SendReminders::class, ExpireDeposits::class]);

        RestaurantNav::add('orders', 'panel.nav.reservations', 'reservations.index', 'reservations.index', icon: 'clock', can: 'orders.create');
        RestaurantNav::add('settings', 'panel.nav.reservation_settings', 'reservations.settings', 'reservations.settings', icon: 'sliders', can: 'reservations.manage');

        $vars = ['name', 'restaurant', 'when', 'party', 'manage_url'];
        $sample = ['name' => 'Sam', 'restaurant' => 'Bella Italia', 'when' => 'Friday 12 June, 20:00', 'party' => '4', 'manage_url' => 'https://example.com/r/bella/reserve/abc'];
        foreach ([
            'reservation_received' => ['Reservation received (guest)', 'We got your booking · {{restaurant}}', "Hi {{name}},\n\nWe received your booking for **{{party}}** at **{{restaurant}}** on **{{when}}**. We will confirm it shortly.\n\n[See or cancel your booking]({{manage_url}})"],
            'reservation_confirmed' => ['Reservation confirmed (guest)', 'Your table is booked · {{restaurant}}', "Hi {{name}},\n\nYour table for **{{party}}** at **{{restaurant}}** is confirmed for **{{when}}**.\n\n[See or cancel your booking]({{manage_url}})"],
            'reservation_cancelled' => ['Reservation cancelled (guest)', 'Booking cancelled · {{restaurant}}', "Hi {{name}},\n\nYour booking for **{{party}}** at **{{restaurant}}** on **{{when}}** was cancelled."],
            'reservation_reminder' => ['Reservation reminder (guest)', 'See you soon · {{restaurant}}', "Hi {{name}},\n\nA reminder: your table for **{{party}}** at **{{restaurant}}** is booked for **{{when}}**.\n\n[Change or cancel]({{manage_url}})"],
        ] as $key => [$label, $subject, $body]) {
            EmailTemplateRegistry::register($key, ['label' => $label, 'required' => false, 'variables' => $vars, 'sample' => $sample, 'subject' => $subject, 'body' => $body]);
        }
    }
}
