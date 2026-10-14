{{-- Profile fields. Expects $restaurant and $profile (RestaurantProfile). --}}
<x-ui.input name="name" :label="__('onboarding.restaurant_name')" :value="$restaurant->name" required />
<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input name="phone" type="tel" :label="__('onboarding.phone')" :value="$restaurant->phone" autocomplete="tel" />
    <x-ui.input name="city" :label="__('onboarding.city')" :value="$restaurant->city" autocomplete="address-level2" />
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input name="address" :label="__('onboarding.address')" :value="$restaurant->address" autocomplete="street-address" />
    <x-ui.input name="country" :label="__('onboarding.country')" :value="$restaurant->country" autocomplete="country-name" />
</div>
<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="currency_code" class="mb-1.5 block text-sm font-medium">{{ __('onboarding.currency') }}</label>
        <select id="currency_code" name="currency_code" class="field">
            <option value="">—</option>
            @foreach ($profile->currencies() as $code => $label)<option value="{{ $code }}" @selected(old('currency_code', $restaurant->currency_code) === $code)>{{ $label }}</option>@endforeach
        </select>
        @error('currency_code')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="timezone" class="mb-1.5 block text-sm font-medium">{{ __('onboarding.timezone') }}</label>
        <select id="timezone" name="timezone" class="field">
            @foreach ($profile->timezones() as $tz)<option value="{{ $tz }}" @selected(old('timezone', $restaurant->timezone) === $tz)>{{ $tz }}</option>@endforeach
        </select>
        @error('timezone')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="locale" class="mb-1.5 block text-sm font-medium">{{ __('onboarding.language') }}</label>
        <select id="locale" name="locale" class="field">
            @foreach ($profile->languages() as $code => $label)<option value="{{ $code }}" @selected(old('locale', $restaurant->locale) === $code)>{{ $label }}</option>@endforeach
        </select>
        @error('locale')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
</div>
<fieldset>
    <legend class="mb-1.5 text-sm font-medium">{{ __('onboarding.menu_languages') }}</legend>
    <div class="flex flex-wrap gap-x-5 gap-y-2">
        @foreach ($profile->languages() as $code => $label)
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="menu_locales[]" value="{{ $code }}" class="check" @checked(in_array($code, old('menu_locales', $restaurant->menuLocales())))>{{ $label }}</label>
        @endforeach
    </div>
    <p class="mt-1.5 text-xs text-muted">{{ __('onboarding.menu_languages_hint') }}</p>
</fieldset>
