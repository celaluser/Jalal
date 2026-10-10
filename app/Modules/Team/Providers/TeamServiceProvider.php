<?php

namespace App\Modules\Team\Providers;

use App\Models\User;
use App\Modules\Billing\Support\UsageRegistry;
use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Support\RestaurantNav;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\ServiceProvider;

class TeamServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'team');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        RestaurantNav::add('settings', 'panel.nav.team', 'team.index', 'team.*', icon: 'users', can: 'staff.view');

        UsageRegistry::register('staff', fn (Restaurant $r) => User::where('restaurant_id', $r->id)->count());

        EmailTemplateRegistry::register('staff_invite', [
            'label' => 'Staff invitation', 'required' => false,
            'variables' => ['name', 'inviter', 'restaurant', 'role', 'action_url', 'expires_minutes', 'app_name'],
            'sample' => ['name' => 'Sam', 'inviter' => 'Ada', 'restaurant' => 'Bella Italia', 'role' => 'waiter', 'action_url' => 'https://example.com/reset-password/token', 'expires_minutes' => '60', 'app_name' => 'QR Menu'],
            'subject' => '{{inviter}} invited you to {{restaurant}} on {{app_name}}',
            'body' => "Hi {{name}},\n\n{{inviter}} added you to **{{restaurant}}** as **{{role}}**. Choose your password to get started (the link works for {{expires_minutes}} minutes).\n\n[Set my password]({{action_url}})\n\nIf you were not expecting this, you can ignore this message.",
        ]);
    }
}
