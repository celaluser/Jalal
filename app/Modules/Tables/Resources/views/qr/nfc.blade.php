<x-layouts.app :title="__('tables.nfc_title')">
    <x-ui.page-header :title="__('tables.nfc_title')" :description="__('tables.nfc_sub')" :back="['url' => route('tables.qr'), 'label' => __('tables.qr_title')]">
        <x-slot:actions><a class="btn btn-secondary" href="{{ route('tables.nfc.csv') }}"><x-ui.icon name="download" size="4" />{{ __('tables.nfc_csv') }}</a></x-slot:actions>
    </x-ui.page-header>

    <div x-data="nfcWriter()" class="max-w-2xl space-y-4">
        <x-ui.alert type="info">{{ __('tables.nfc_help') }}</x-ui.alert>
        <p class="text-sm" x-show="!supported" x-cloak>{{ __('tables.nfc_unsupported') }}</p>
        <p class="text-sm font-medium" x-show="message" x-text="message" role="status" x-cloak></p>

        <ul class="grid gap-2">
            @foreach (array_merge([['name' => __('tables.menu_code'), 'url' => $menuUrl]], $links) as $link)
                <li class="card flex flex-wrap items-center gap-3 p-3">
                    <div class="min-w-0 flex-1"><p class="font-medium">{{ $link['name'] }}</p><p class="truncate text-xs text-muted" dir="ltr">{{ $link['url'] }}</p></div>
                    <button type="button" class="btn btn-secondary btn-sm" x-on:click="copy(@js($link['url']))">{{ __('tables.nfc_copy') }}</button>
                    <button type="button" class="btn btn-primary btn-sm" x-show="supported" x-cloak x-on:click="write(@js($link['url']))">{{ __('tables.nfc_write') }}</button>
                </li>
            @endforeach
        </ul>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('nfcWriter', () => ({
                supported: 'NDEFReader' in window, message: '',
                async copy(url) { try { await navigator.clipboard.writeText(url); this.message = @js(__('tables.nfc_copied')); } catch (e) { this.message = url; } },
                async write(url) {
                    this.message = @js(__('tables.nfc_hold'));
                    try { await new NDEFReader().write({ records: [{ recordType: 'url', data: url }] }); this.message = @js(__('tables.nfc_done')); }
                    catch (e) { this.message = @js(__('tables.nfc_failed')); }
                },
            }));
        });
    </script>
</x-layouts.app>
