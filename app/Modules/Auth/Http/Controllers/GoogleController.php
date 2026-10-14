<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

/**
 * "Sign in with Google". Enabled and configured by the super admin in settings; never
 * creates accounts (restaurants register through the normal flow), only links by e-mail.
 */
class GoogleController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function redirect(): RedirectResponse
    {
        return $this->provider()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = $this->provider()->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => __('auth.google_failed')]);
        }

        // An address is only trusted for linking an existing account when Google says it is verified.
        $verified = (bool) ($google->user['email_verified'] ?? false);
        $user = User::where('google_id', $google->getId())->first()
            ?? ($verified && $google->getEmail() ? User::where('email', $google->getEmail())->first() : null);

        if (! $user || $user->disabled_at !== null || $user->restaurant?->isSuspended()) {
            return redirect()->route('login')->withErrors(['email' => __('auth.google_no_account')]);
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $google->getId()])->save();
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor.user_id', $user->id);
            $request->session()->put('two_factor.remember', false);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function provider(): GoogleProvider
    {
        abort_unless($this->settings->get('auth.google_enabled', false), 404);

        config(['services.google' => [
            'client_id' => $this->settings->get('auth.google_client_id'),
            'client_secret' => $this->settings->get('auth.google_client_secret'),
            'redirect' => route('google.callback'),
        ]]);

        return Socialite::driver('google');
    }
}
