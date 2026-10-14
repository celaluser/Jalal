@php
    $editing = ! empty($theme['key']);
    $v = fn ($k) => old($k, $theme[$k] ?? null);
    $init = ['bg' => $v('bg'), 'bg2' => $v('bg2'), 'surface' => $v('surface'), 'fg' => $v('fg'), 'muted' => $v('muted'), 'line' => $v('line'), 'bg_style' => $v('bg_style'), 'card' => $v('card'), 'radius' => $v('radius'), 'scrollbar' => $v('scrollbar')];
@endphp
<x-layouts.admin :title="__('admin.themes.form_title')">
    <x-ui.page-header :title="$editing ? __('admin.themes.form_title').': '.$theme['name'] : __('admin.themes.new')" :back="['url' => route('admin.themes.index'), 'label' => __('admin.themes.title')]" />

    <form method="POST" action="{{ $editing ? route('admin.themes.update', $theme['key']) : route('admin.themes.store') }}" class="grid gap-5 lg:grid-cols-5"
          x-data='{ t: @json($init), radii: @json(config('themes.radii')), get page() { return this.t.bg_style === "solid" ? this.t.bg : "linear-gradient(160deg," + this.t.bg + "," + this.t.bg2 + ")"; } }'>
        @csrf @if ($editing) @method('PUT') @endif

        <div class="space-y-5 lg:col-span-3">
            <x-ui.card>
                <x-ui.input name="name" :label="__('admin.themes.name')" :value="$theme['name']" maxlength="40" required />
                <label class="mt-4 flex items-center gap-2.5 text-sm"><input type="checkbox" name="dark" value="1" class="check" @checked(old('dark', $theme['dark']))>{{ __('admin.themes.dark') }}</label>
            </x-ui.card>

            <x-ui.card :title="__('admin.themes.colours')">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (\App\Modules\Menu\Services\ThemeLibrary::COLORS as $c)
                        <div>
                            <label for="{{ $c }}" class="mb-1.5 block text-sm font-medium">{{ __('admin.themes.'.$c) }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="t.{{ $c }}" class="h-10 w-12 cursor-pointer rounded border border-line-strong bg-transparent p-0.5" aria-label="{{ __('admin.themes.'.$c) }}">
                                <input id="{{ $c }}" name="{{ $c }}" x-model="t.{{ $c }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" class="field font-mono" required @error($c) aria-invalid="true" @enderror>
                            </div>
                            @error($c)<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.themes.style')">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $selects = [
                            ['font', array_keys(config('themes.fonts')), 'menu.appearance.fonts.', false], ['layout', config('themes.layouts'), 'menu.appearance.layouts.', false], ['radius', array_keys(config('themes.radii')), 'menu.appearance.radii.', true],
                            ['bg_style', config('themes.backgrounds'), 'admin.themes.bg_styles.', true], ['card', config('themes.cards'), 'menu.appearance.cards.', true], ['scrollbar', config('themes.scrollbars'), 'menu.appearance.scrollbars.', true], ['reveal', config('themes.reveals'), 'menu.appearance.reveals.', false],
                            ['decor', config('themes.decors'), 'menu.appearance.decors.', false], ['button', config('themes.buttons'), 'menu.appearance.buttons.', false], ['heading', config('themes.headings'), 'menu.appearance.headings.', false], ['hover', config('themes.hovers'), 'menu.appearance.hovers.', false],
                        ];
                    @endphp
                    @foreach ($selects as [$field, $values, $prefix, $bind])
                        <div>
                            <label for="{{ $field }}" class="mb-1.5 block text-sm font-medium">{{ __('admin.themes.'.$field) }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" class="field" @if ($bind) x-model="t.{{ $field }}" @endif>
                                @foreach ($values as $val)<option value="{{ $val }}" @selected($v($field) === $val)>{{ __($prefix.$val) }}</option>@endforeach
                            </select>
                            @error($field)<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 space-y-3">
                    <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="progress" value="1" class="check" @checked(old('progress', $theme['progress']))>{{ __('admin.themes.progress') }}</label>
                    <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="animated_bg" value="1" class="check" @checked(old('animated_bg', $theme['animated_bg']))>{{ __('admin.themes.animated_bg') }}</label>
                </div>
                <p class="mt-4 text-xs text-muted">{{ __('admin.themes.note') }}</p>
            </x-ui.card>

            <div class="flex gap-3"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button><a href="{{ route('admin.themes.index') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a></div>
        </div>

        <aside class="lg:col-span-2">
            <div class="sticky top-4">
                <p class="mb-2 text-sm font-medium">{{ __('admin.themes.preview') }}</p>
                <div class="relative overflow-hidden rounded-3xl border border-line-strong p-4" :style="{ background: page }">
                    <span class="block h-3 w-24 rounded" :style="{ background: t.fg }"></span>
                    <span class="mt-1.5 block h-2 w-40 rounded" :style="{ background: t.muted }"></span>
                    <div class="mt-4 space-y-3 pe-3">
                        <template x-for="n in 3" :key="n">
                            <div class="p-3" :style="{ background: t.surface, border: '1px solid ' + t.line, borderRadius: radii[t.radius], backdropFilter: t.card === 'glass' ? 'blur(10px)' : 'none', boxShadow: t.card === 'soft' ? '0 8px 18px -8px rgb(0 0 0/.3)' : (t.card === 'outline' ? '0 0 16px -2px #ffb020' : 'none'), opacity: t.card === 'glass' ? .8 : 1 }">
                                <span class="block h-2.5 w-24 rounded" :style="{ background: t.fg }"></span>
                                <span class="mt-2 block h-2 w-32 rounded" :style="{ background: t.muted }"></span>
                            </div>
                        </template>
                    </div>
                    <span class="absolute bottom-4 end-1.5 top-4 w-1.5 rounded-full" :style="{ background: t.scrollbar === 'default' ? 'transparent' : 'linear-gradient(#ffb020,#ff7a3d)', boxShadow: t.scrollbar === 'glow' ? '0 0 10px #ffb020' : 'none', opacity: t.scrollbar === 'slim' ? .5 : 1 }"></span>
                </div>
            </div>
        </aside>
    </form>
</x-layouts.admin>
