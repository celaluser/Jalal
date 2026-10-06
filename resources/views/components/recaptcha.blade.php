@if (platform_setting('security.recaptcha_enabled') === '1' && platform_setting('security.recaptcha_site_key'))
    <div>
        <div class="g-recaptcha" data-sitekey="{{ platform_setting('security.recaptcha_site_key') }}"></div>
        @error('g-recaptcha-response')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
    @once
        @push('scripts')<script src="https://www.google.com/recaptcha/api.js" async defer></script>@endpush
    @endonce
@endif
