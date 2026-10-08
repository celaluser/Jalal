<x-layouts.app :title="__('website.title')">
    <x-ui.page-header :title="__('website.title')" :description="__('website.sub')" />

    <form method="POST" action="{{ route('site.update') }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <x-ui.card :title="__('website.visibility')">
            <div class="space-y-3">
                <x-ui.checkbox name="site_enabled" :label="__('website.enabled')" :checked="$s['site_enabled']" />
                <p class="-mt-2 text-xs text-muted">{{ __('website.enabled_help', ['url' => $restaurant->publicUrl('about')]) }}</p>
                <x-ui.checkbox name="indexable" :label="__('website.indexable')" :checked="$s['indexable']" />
                <p class="-mt-2 text-xs text-muted">{{ __('website.indexable_help', ['url' => $restaurant->publicUrl('sitemap.xml')]) }}</p>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('website.texts')">
            <div class="space-y-5">
                @foreach ($locales as $code)
                    <fieldset class="space-y-3 rounded-lg border border-line p-4">
                        <legend class="px-1 text-sm font-semibold uppercase" dir="ltr">{{ $code }}</legend>
                        <x-ui.input :name="'seo_title['.$code.']'" :value="old('seo_title.'.$code, $s['seo_title'][$code] ?? '')" :label="__('website.seo_title')" maxlength="70" />
                        <x-ui.input :name="'seo_description['.$code.']'" :value="old('seo_description.'.$code, $s['seo_description'][$code] ?? '')" :label="__('website.seo_description')" maxlength="170" />
                        <div><label class="mb-1.5 block text-sm font-medium" for="about_{{ $code }}">{{ __('website.about') }}</label>
                            <textarea id="about_{{ $code }}" name="about[{{ $code }}]" rows="4" maxlength="3000" class="field">{{ old('about.'.$code, $s['about'][$code] ?? '') }}</textarea></div>
                    </fieldset>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card :title="__('website.hours')">
            <p class="mb-3 text-sm text-muted">{{ __('website.hours_help') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (range(0, 6) as $i)<x-ui.input :name="'hours['.$i.']'" :value="old('hours.'.$i, $s['hours'][$i] ?? '')" :label="__('customer.about_day_'.$i)" placeholder="11:00-23:00" dir="ltr" maxlength="60" />@endforeach
            </div>
        </x-ui.card>

        <x-ui.card :title="__('website.details')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="cuisine" :value="$s['cuisine']" :label="__('website.cuisine')" placeholder="Italian, Pizza" maxlength="80" />
                <div><label class="mb-1.5 block text-sm font-medium" for="price_range">{{ __('website.price_range') }}</label>
                    <select id="price_range" name="price_range" class="field"><option value="">–</option>@foreach (['$', '$$', '$$$', '$$$$'] as $p)<option value="{{ $p }}" @selected($s['price_range'] === $p)>{{ $p }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><x-ui.input name="map_url" type="url" :value="$s['map_url']" :label="__('website.map_url')" :hint="__('website.map_url_help')" dir="ltr" maxlength="500" /></div>
            </div>
        </x-ui.card>

        <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
    </form>
</x-layouts.app>
