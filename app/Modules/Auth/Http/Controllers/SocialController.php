<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Services\AppleSignIn;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Facebook" and "Continue with Apple" for people who already have an account (restaurants register the
 * normal way). Each is switched on by the platform owner in settings; accounts are linked by provider id, then by e-mail.
 */
class SocialController extends Controller
{
    private const PROVIDERS = ['facebook', 'apple'];

    public function __construct(private readonly SettingsService $settings, private readonly AppleSignIn $apple) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->guard($provider);

        if ($provider === 'apple') {
            return redirect()->away($this->apple->authorizeUrl(route('social.callback', 'apple')));
        }

        return $this->facebook()->redirect();
    }

    /** GET for Facebook; Apple posts the result back (form_post). */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->guard($provider);
        abort_if($provider === 'apple' && ! $this->apple->validState($request->input('state')), 400);

        try {
            [$id, $email] = $provider === 'apple' ? $this->fromApple($request) : $this->fromFacebook();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => __('auth.social_failed', ['provider' => ucfirst($provider)])]);
        }

        $column = $provider.'_id';
        $user = User::where($column, $id)->first() ?? ($email ? User::where('email', $email)->first() : null);

        if (! $user || $user->disabled_at !== null || $user->restaurant?->isSuspended()) {
            return redirect()->route('login')->withErrors(['email' => __('auth.social_no_account', ['provider' => ucfirst($provider)])]);
        }

        if ($user->{$column} === null) {
            $user->forceFill([$column => $id])->save();
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** @return array{0: string, 1: ?string} */
    private function fromApple(Request $request): array
    {
        $identity = $this->apple->identity((string) $request->input('code'), route('social.callback', 'apple'));

        return [$identity['id'], $identity['email']];
    }

    /** @return array{0: string, 1: ?string} */
    private function fromFacebook(): array
    {
        $user = $this->facebook()->user();

        return [(string) $user->getId(), $user->getEmail() ? strtolower($user->getEmail()) : null];
    }

    private function facebook()
    {
        config(['services.facebook' => [
            'client_id' => $this->settings->get('auth.facebook_client_id'),
            'client_secret' => $this->settings->get('auth.facebook_client_secret'),
            'redirect' => route('social.callback', 'facebook'),
        ]]);

        return Socialite::driver('facebook')->scopes(['email']);
    }

    private function guard(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true) && $this->settings->get("auth.{$provider}_enabled", false), 404);
    }
}
