@if (platform_setting('security.turnstile_enabled') === '1' && platform_setting('security.turnstile_site_key'))
    <div>
        <div class="cf-turnstile" data-sitekey="{{ platform_setting('security.turnstile_site_key') }}"></div>
        @error('g-recaptcha-response')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
    @once
        @push('scripts')<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endpush
    @endonce
@elseif (platform_setting('security.recaptcha_enabled') === '1' && platform_setting('security.recaptcha_site_key') && platform_setting('security.recaptcha_version') === 'v3')
    {{-- reCAPTCHA v3: invisible. A token is fetched when the form is sent; the server checks the score. --}}
    <div x-data="{ ready: false }" x-init="
        const f = $el.closest('form'); const key = @js(platform_setting('security.recaptcha_site_key'));
        f.addEventListener('submit', (e) => {
            if (f.querySelector('[name=g-recaptcha-response]').value || !window.grecaptcha) { return; }
            e.preventDefault();
            grecaptcha.ready(() => grecaptcha.execute(key, { action: 'submit' }).then((t) => { f.querySelector('[name=g-recaptcha-response]').value = t; f.requestSubmit ? f.requestSubmit() : f.submit(); }));
        });">
        <input type="hidden" name="g-recaptcha-response" value="">
        @error('g-recaptcha-response')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
        <p class="text-[11px] text-muted">{!! __('auth.recaptcha_notice') !!}</p>
    </div>
    @once
        @push('scripts')<script src="https://www.google.com/recaptcha/api.js?render={{ platform_setting('security.recaptcha_site_key') }}" async defer></script>@endpush
    @endonce
@elseif (platform_setting('security.recaptcha_enabled') === '1' && platform_setting('security.recaptcha_site_key'))
    <div>
        <div class="g-recaptcha" data-sitekey="{{ platform_setting('security.recaptcha_site_key') }}"></div>
        @error('g-recaptcha-response')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
    @once
        @push('scripts')<script src="https://www.google.com/recaptcha/api.js" async defer></script>@endpush
    @endonce
@endif
