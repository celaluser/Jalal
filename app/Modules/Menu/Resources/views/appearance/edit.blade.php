@php
    $fonts = config('themes.fonts');
    $radii = config('themes.radii');
    $sampleItems = $sample ?: [
        ['name' => __('menu.appearance.sample_item').' 1', 'description' => __('menu.appearance.sample_text'), 'price' => 12.0, 'image' => null],
        ['name' => __('menu.appearance.sample_item').' 2', 'description' => __('menu.appearance.sample_text'), 'price' => 9.5, 'image' => null],
        ['name' => __('menu.appearance.sample_item').' 3', 'description' => __('menu.appearance.sample_text'), 'price' => 7.0, 'image' => null],
    ];
    $sampleItems = collect($sampleItems)->map(fn ($p) => ['name' => $p['name'], 'description' => $p['description'], 'price' => $restaurant->money($p['price'])])->all();
@endphp
<x-layouts.app :title="__('menu.appearance.title')">
    <x-ui.page-header :title="__('menu.appearance.title')" :description="__('menu.appearance.subtitle')" />

    <form method="POST" action="{{ route('appearance.update') }}" class="grid grid-cols-1 gap-5 lg:grid-cols-5"
          x-data="menuAppearance(@js($settings), @js($themes), @js($fonts), @js($radii), @js($accent), @js($sampleItems), @js($restaurant->name))">
        @csrf @method('PUT')

        <div class="space-y-5 lg:col-span-3">
            <x-ui.card :title="__('menu.appearance.theme')" :description="__('menu.appearance.brand_note')">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3" role="radiogroup">
                    @foreach ($themes as $key => $t)
                        <label class="cursor-pointer">
                            <input type="radio" name="theme" value="{{ $key }}" x-model="theme" class="peer sr-only">
                            <span class="block overflow-hidden rounded-2xl border border-line-strong transition peer-checked:border-accent-600 peer-checked:ring-2 peer-checked:ring-accent-600/30 peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500">
                                <span class="block p-3" style="background: {{ $t['bg'] }}">
                                    <span class="block h-2 w-10 rounded" style="background: {{ $t['fg'] }}"></span>
                                    <span class="mt-2 flex gap-1.5"><span class="h-8 flex-1 rounded" style="background: {{ $t['surface'] }}; border: 1px solid {{ $t['line'] }}"></span><span class="h-8 flex-1 rounded" style="background: {{ $t['surface'] }}; border: 1px solid {{ $t['line'] }}"></span></span>
                                    <span class="mt-2 block h-4 rounded" style="background: {{ $accent }}"></span>
                                </span>
                                <span class="block bg-surface px-3 py-2 text-sm font-medium">{{ __('menu.appearance.themes.'.$key) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('theme')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </x-ui.card>

            <x-ui.card>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([['font', array_keys($fonts), 'fonts'], ['layout', config('themes.layouts'), 'layouts'], ['radius', array_keys($radii), 'radii'], ['hero', config('themes.heroes'), 'heroes'], ['scroll', config('themes.scrolls'), 'scrolls']] as [$field, $values, $group])
                        <div>
                            <label for="{{ $field }}" class="mb-1.5 block text-sm font-medium">{{ __('menu.appearance.'.$field) }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" class="field" x-model="{{ $field }}">
                                @foreach ($values as $v)<option value="{{ $v }}">{{ __('menu.appearance.'.$group.'.'.$v) }}</option>@endforeach
                            </select>
                            @error($field)<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 space-y-3">
                    <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="show_images" value="1" class="check" x-model="show_images">{{ __('menu.appearance.show_images') }}</label>
                    <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="dark_toggle" value="1" class="check" @checked($settings['dark_toggle'])>{{ __('menu.appearance.dark_toggle') }}</label>
                    <div>
                        <label class="flex items-center gap-2.5 text-sm {{ $canRemoveCredit ? '' : 'opacity-60' }}"><input type="checkbox" name="hide_credit" value="1" class="check" @checked(! $settings['show_credit']) @disabled(! $canRemoveCredit)>{{ __('menu.appearance.hide_credit') }}</label>
                        @unless ($canRemoveCredit)<p class="mt-1 text-xs text-muted">{{ __('menu.appearance.hide_credit_locked') }}</p>@endunless
                    </div>
                </div>
            </x-ui.card>

            <div class="flex justify-end"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button></div>
        </div>

        {{-- Phone preview, driven by the same tokens the customer menu uses --}}
        <div class="lg:col-span-2">
            <p class="eyebrow mb-2">{{ __('menu.appearance.preview') }}</p>
            <div class="mx-auto w-full max-w-[19rem] overflow-hidden rounded-[2rem] border-[6px] border-ink-950 shadow-pop dark:border-ink-700" aria-hidden="true">
                <div class="min-h-[30rem] p-4 transition-colors" :style="`background:${t.bg};color:${t.fg};font-family:${fonts[font]}`">
                    <div class="mb-4 flex items-center gap-2.5">
                        <span class="grid size-9 place-items-center text-sm font-bold" :style="`background:${accent};color:${accentFg};border-radius:${radii[radius]}`" x-text="name.slice(0, 1).toUpperCase()"></span>
                        <span class="truncate font-semibold" x-text="name"></span>
                    </div>
                    <div :class="layout === 'grid' ? 'grid grid-cols-2 gap-2.5' : 'space-y-2.5'">
                        <template x-for="item in items" :key="item.name">
                            <div :style="`background:${t.surface};border:1px solid ${t.line};border-radius:${radii[radius]}`" :class="layout === 'list' ? 'flex items-center gap-3 p-3' : (layout === 'grid' ? 'p-2.5' : 'overflow-hidden')">
                                <div x-show="show_images" :style="`background:${t.line};border-radius:calc(${radii[radius]} * .6)`" :class="layout === 'list' ? 'order-2 size-14 shrink-0' : (layout === 'grid' ? 'mb-2 h-16' : 'h-20 rounded-none')"></div>
                                <div :class="layout === 'cards' ? 'p-3' : 'min-w-0 flex-1'">
                                    <p class="truncate text-sm font-semibold" x-text="item.name"></p>
                                    <p class="mt-0.5 truncate text-xs" :style="`color:${t.muted}`" x-text="item.description"></p>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold" x-text="item.price"></span>
                                        <span class="px-2.5 py-1 text-xs font-semibold" :style="`background:${accent};color:${accentFg};border-radius:${radii[radius]}`">{{ __('menu.appearance.add') }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            const lum = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)).reduce((s, c, i) => s + c * [0.2126, 0.7152, 0.0722][i], 0);
            const ratio = (a, b) => (Math.max(lum(a), lum(b)) + 0.05) / (Math.min(lum(a), lum(b)) + 0.05);
            Alpine.data('menuAppearance', (s, themes, fonts, radii, accent, items, name) => ({
                ...s, themes, fonts, radii, accent, items, name,
                get t() { return this.themes[this.theme]; },
                get accentFg() { return ratio(this.accent, '#0f1115') >= ratio(this.accent, '#ffffff') ? '#0f1115' : '#ffffff'; },
            }));
        });
    </script>
</x-layouts.app>
