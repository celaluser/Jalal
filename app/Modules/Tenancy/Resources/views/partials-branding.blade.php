{{-- Branding fields with a live preview. Expects $restaurant. --}}
<div x-data="{ color: @js(old('color', $restaurant->brandColor())), logo: @js($restaurant->logo?->url()), name: @js($restaurant->name) }" class="grid gap-6 sm:grid-cols-2">
    <div class="space-y-5">
        <div>
            <label for="logo" class="mb-1.5 block text-sm font-medium">{{ __('onboarding.logo') }}</label>
            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="field file:me-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                   x-on:change="logo = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : logo">
            <p class="mt-1.5 text-xs text-muted">{{ __('onboarding.logo_hint') }}</p>
            @error('logo')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            @if ($restaurant->logo_media_id)
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1" class="check" x-on:change="if ($event.target.checked) logo = null">{{ __('onboarding.remove_logo') }}</label>
            @endif
        </div>
        <div>
            <label for="color" class="mb-1.5 block text-sm font-medium">{{ __('onboarding.color') }}</label>
            <div class="flex items-center gap-3">
                <input id="color" name="color" type="color" x-model="color" class="size-11 cursor-pointer rounded-xl border border-line-strong bg-surface p-1">
                <code class="rounded-lg bg-surface-2 px-2 py-1 text-sm" x-text="color" dir="ltr"></code>
            </div>
            <p class="mt-1.5 text-xs text-muted">{{ __('onboarding.color_hint') }}</p>
            @error('color')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Preview of the customer menu header --}}
    <div aria-hidden="true">
        <p class="eyebrow mb-2">{{ __('onboarding.preview') }}</p>
        <div class="mx-auto w-full max-w-[16rem] overflow-hidden rounded-[1.5rem] border-4 border-ink-950 bg-surface shadow-pop dark:border-ink-700">
            <div class="flex items-center gap-3 p-4" :style="`background:${color}`">
                <template x-if="logo"><img :src="logo" alt="" class="size-10 rounded-xl bg-white object-cover"></template>
                <template x-if="!logo"><span class="grid size-10 place-items-center rounded-xl bg-white/90 text-sm font-bold text-ink-950" x-text="name.slice(0, 1).toUpperCase()"></span></template>
                <span class="display truncate text-base font-semibold text-ink-950" x-text="name"></span>
            </div>
            <div class="space-y-2 p-4">
                <div class="h-3 w-24 rounded bg-surface-2"></div>
                <div class="flex gap-2"><div class="h-12 flex-1 rounded-xl bg-surface-2"></div><div class="h-12 flex-1 rounded-xl bg-surface-2"></div></div>
                <div class="h-8 rounded-xl" :style="`background:${color}`"></div>
            </div>
        </div>
    </div>
</div>
