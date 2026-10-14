<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /** Second step of login. */
    public function challenge(Request $request): View|RedirectResponse
    {
        return $request->session()->has('two_factor.user_id')
            ? view('auth-module::two-factor-challenge')
            : redirect()->route('login');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = User::find($request->session()->get('two_factor.user_id'));

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('login');
        }

        if (! $this->twoFactor->attempt($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
        }

        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor.user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** Profile screen: start setup (shows QR) or show status. */
    public function show(Request $request): View
    {
        $user = $request->user();
        $secret = null;
        $qr = null;

        if (! $user->hasTwoFactorEnabled()) {
            $secret = $request->session()->get('two_factor.pending_secret') ?? $this->twoFactor->generateSecret();
            $request->session()->put('two_factor.pending_secret', $secret);
            $qr = $this->twoFactor->qrCodeSvg($user, $secret);
        }

        return view('auth-module::two-factor-settings', [
            'secret' => $secret,
            'qr' => $qr,
            'recoveryCodes' => $request->session()->pull('two_factor.new_codes'),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $secret = $request->session()->get('two_factor.pending_secret');

        if (! $secret || ! $this->twoFactor->verify($secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
        }

        $codes = $this->twoFactor->enable($request->user(), $secret);
        $request->session()->forget('two_factor.pending_secret');
        $request->session()->flash('two_factor.new_codes', $codes);

        return redirect()->route('two-factor.show');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->string('password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        $this->twoFactor->disable($request->user());

        return redirect()->route('two-factor.show');
    }
}
