<?php

namespace App\Modules\Auth\Listeners;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use Illuminate\Auth\Events\Registered;

class SendWelcomeEmail
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        SafeMail::send($user, new TemplatedMail('welcome', [
            'name' => $user->name,
            'restaurant' => $user->restaurant?->name ?? '',
            'login_url' => route('login'),
        ], $user->locale));
    }
}
