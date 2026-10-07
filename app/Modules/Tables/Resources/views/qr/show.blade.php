<x-layouts.app :title="__('tables.qr_title')">
    <x-ui.page-header :title="__('tables.qr_title')" :description="__('tables.qr_subtitle')" :back="['url' => route('tables.index'), 'label' => __('tables.title')]" />

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
        <form method="POST" action="{{ route('tables.qr.update') }}" class="space-y-5 lg:col-span-3"
              x-data="qrDesign(@js(route('tables.qr.preview')), @js($settings))">
            @csrf @method('PUT')
            <x-ui.card :title="__('tables.style')">
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="fg" class="mb-1.5 block text-sm font-medium">{{ __('tables.fg') }}</label>
                            <div class="flex items-center gap-3"><input id="fg" name="fg" type="color" x-model="fg" class="size-11 cursor-pointer rounded-xl border border-line-strong bg-surface p-1"><code class="rounded-lg bg-surface-2 px-2 py-1 text-sm" x-text="fg" dir="ltr"></code></div>
                        </div>
                        <div>
                            <label for="bg" class="mb-1.5 block text-sm font-medium">{{ __('tables.bg') }}</label>
                            <div class="flex items-center gap-3"><input id="bg" name="bg" type="color" x-model="bg" class="size-11 cursor-pointer rounded-xl border border-line-strong bg-surface p-1"><code class="rounded-lg bg-surface-2 px-2 py-1 text-sm" x-text="bg" dir="ltr"></code></div>
                        </div>
                    </div>
                    <p class="text-sm text-red-600 dark:text-red-400" x-show="error" x-text="error" x-cloak role="alert"></p>
                    @error('fg')<p class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>@enderror

                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium">{{ __('tables.shape') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Modules\Tables\Qr\QrStyle::SHAPES as $shape)
                                <label class="cursor-pointer"><input type="radio" name="shape" value="{{ $shape }}" x-model="shape" class="peer sr-only">
                                    <span class="inline-flex rounded-xl border border-line-strong px-4 py-2 text-sm transition peer-checked:border-accent-600 peer-checked:bg-accent-50 peer-checked:font-semibold peer-checked:text-accent-800 peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500 dark:peer-checked:bg-accent-900/30 dark:peer-checked:text-accent-200">{{ __('tables.shape_'.$shape) }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div>
                        <label class="flex items-center gap-2.5 text-sm {{ $hasLogo ? 'cursor-pointer' : 'opacity-60' }}"><input type="checkbox" name="logo" value="1" class="check" x-model="logo" @disabled(! $hasLogo)>{{ __('tables.logo') }}</label>
                        @unless ($hasLogo)<p class="mt-1 text-xs text-muted">{{ __('tables.logo_missing') }} <a class="link" href="{{ route('restaurant.settings') }}">{{ __('panel.nav.restaurant') }}</a></p>@endunless
                    </div>

                    <x-ui.input name="caption" :label="__('tables.caption')" :value="$settings['caption']" maxlength="60" :hint="__('tables.caption_hint')" />
                </div>
            </x-ui.card>
            <div class="flex items-center justify-between gap-3">
                <p class="flex items-start gap-2 text-xs text-muted"><x-ui.icon name="info" size="4" class="mt-0.5 shrink-0" />{{ __('tables.scan_note') }}</p>
                <x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button>
            </div>
        </form>

        <div class="space-y-5 lg:col-span-2">
            <x-ui.card :title="__('tables.preview')">
                <div class="mx-auto max-w-[17rem]">
                    <div class="overflow-hidden rounded-2xl ring-1 ring-line [&>svg]:block [&>svg]:size-full" id="qr-preview" aria-label="{{ __('tables.preview') }}">{!! $svg !!}</div>
                    <p class="mt-3 break-all text-center text-xs text-muted" dir="ltr">{{ $previewUrl }}</p>
                    <p class="display mt-2 text-center text-sm font-semibold">{{ $caption }}</p>
                </div>
            </x-ui.card>
            <x-ui.card :title="__('tables.downloads')">
                <div class="grid gap-2">
                    @if ($tableCount > 0)
                        <a class="btn btn-primary" href="{{ route('tables.qr.pdf') }}"><x-ui.icon name="download" size="4" />{{ __('tables.pdf_sheet') }}</a>
                        <a class="btn btn-secondary" href="{{ route('tables.qr.zip', 'png') }}">{{ __('tables.zip_png') }}</a>
                        <a class="btn btn-secondary" href="{{ route('tables.qr.zip', 'svg') }}">{{ __('tables.zip_svg') }}</a>
                    @else
                        <p class="text-sm text-muted">{{ __('tables.no_tables_for_print') }}</p>
                    @endif
                </div>
                <div class="mt-5 border-t border-line pt-4">
                    <p class="text-sm font-semibold">{{ __('tables.menu_code') }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ __('tables.menu_code_help') }}</p>
                    <div class="mt-3 flex gap-2">
                        <a class="btn btn-secondary btn-sm" href="{{ route('tables.qr.download', ['menu', 'png']) }}">{{ __('tables.download_png') }}</a>
                        <a class="btn btn-secondary btn-sm" href="{{ route('tables.qr.download', ['menu', 'svg']) }}">{{ __('tables.download_svg') }}</a>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('qrDesign', (url, s) => ({
                fg: s.fg, bg: s.bg, shape: s.shape, logo: !!s.logo, error: '', timer: null,
                init() {
                    ['fg', 'bg', 'shape', 'logo'].forEach((k) => this.$watch(k, () => { clearTimeout(this.timer); this.timer = setTimeout(() => this.refresh(), 250); }));
                },
                async refresh() {
                    const q = new URLSearchParams({ fg: this.fg, bg: this.bg, shape: this.shape, ...(this.logo ? { logo: 1 } : {}) });
                    const res = await fetch(url + '?' + q, { headers: { Accept: 'image/svg+xml, application/json' } });
                    if (res.ok) { this.error = ''; document.getElementById('qr-preview').innerHTML = await res.text(); return; }
                    const body = await res.json().catch(() => ({}));
                    this.error = body.errors?.fg?.[0] || body.message || '';
                },
            }));
        });
    </script>
</x-layouts.app>
