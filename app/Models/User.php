<?php

namespace App\Models;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * restaurant_id is fillable for server-side code only (registration, staff creation);
     * never pass raw request input to create()/fill().
     */
    protected $fillable = ['restaurant_id', 'name', 'email', 'password', 'locale', 'google_id', 'avatar'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * Users are deliberately NOT tenant-scoped: login must find a user before any tenant is
     * known. Restaurant staff lists must therefore always go through Restaurant::users().
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isPlatformUser(): bool
    {
        return $this->restaurant_id === null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** Uses the admin-editable "verify_email" template instead of Laravel's built-in notification. */
    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );

        SafeMail::send($this, new TemplatedMail('verify_email', ['name' => $this->name, 'action_url' => $url], $this->locale));
    }

    /** Uses the admin-editable "password_reset" template. */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);

        SafeMail::send($this, new TemplatedMail('password_reset', [
            'name' => $this->name,
            'action_url' => $url,
            'expires_minutes' => (string) config('auth.passwords.users.expire', 60),
        ], $this->locale));
    }
}
