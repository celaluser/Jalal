@php($sets = ['themes' => $themes, 'features' => $features])
<x-layouts.app :title="__('store.title')">
    <x-ui.page-header :title="__('store.title')" :description="__('store.subtitle')" />
    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <div class="mb-5 flex gap-1 rounded-xl bg-surface-2 p-1 text-sm font-semibold sm:w-fit" role="tablist">
        @foreach (['themes', 'features'] as $t)
            <a href="{{ route('store.index', ['tab' => $t]) }}" role="tab" aria-selected="{{ $tab === $t ? 'true' : 'false' }}" @class(['flex-1 rounded-lg px-4 py-2 text-center sm:flex-none', 'bg-surface shadow-sm' => $tab === $t, 'text-muted' => $tab !== $t])>{{ __('store.tab_'.$t) }} <span class="text-xs text-muted">{{ $sets[$t]->count() }}</span></a>
        @endforeach
    </div>

    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($sets[$tab] as $item)
            @php($theme = $item['kind'] === 'theme' ? (app(\App\Modules\Menu\Services\ThemeLibrary::class)->all()[$item['key']] ?? null) : null)
            <li class="card flex flex-col overflow-hidden">
                @if ($theme)
                    <div class="p-4" style="background: {{ $theme['bg_style'] === 'solid' ? $theme['bg'] : 'linear-gradient(160deg,'.$theme['bg'].','.$theme['bg2'].')' }}">
                        <span class="block h-2.5 w-16 rounded" style="background: {{ $theme['fg'] }}"></span>
                        <span class="mt-3 flex gap-2"><span class="h-12 flex-1" style="background: {{ $theme['surface'] }}; border: 1px solid {{ $theme['line'] }}; border-radius: {{ config('themes.radii')[$theme['radius']] }}"></span><span class="h-12 flex-1" style="background: {{ $theme['surface'] }}; border: 1px solid {{ $theme['line'] }}; border-radius: {{ config('themes.radii')[$theme['radius']] }}"></span></span>
                    </div>
                @endif
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold">{{ $item['name'] }}</h2>@include('store::panel.badge', ['item' => $item])</div>
                    @if ($item['summary'])<p class="text-sm text-muted">{{ $item['summary'] }}</p>@endif
                    <p class="mt-auto text-sm font-medium tnum">@include('store::panel.price', ['item' => $item])</p>
                    <a href="{{ route('store.show', $item['slug']) }}" class="btn {{ $item['state']['status'] === 'buy' ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ $item['state']['status'] === 'buy' ? __('store.view_buy') : __('store.details') }}</a>
                </div>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
