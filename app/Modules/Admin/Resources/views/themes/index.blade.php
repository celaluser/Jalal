<x-layouts.admin :title="__('admin.themes.title')">
    <x-ui.page-header :title="__('admin.themes.title')" :description="__('admin.themes.subtitle')">
        <x-slot:actions><a href="{{ route('admin.themes.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('admin.themes.new') }}</a></x-slot:actions>
    </x-ui.page-header>
    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($themes as $key => $t)
            <li class="card overflow-hidden {{ $t['enabled'] ? '' : 'opacity-60' }}">
                <div class="p-4" style="background: {{ $t['bg_style'] === 'solid' ? $t['bg'] : 'linear-gradient(160deg,'.$t['bg'].','.$t['bg2'].')' }}">
                    <span class="block h-2.5 w-16 rounded" style="background: {{ $t['fg'] }}"></span>
                    <span class="mt-3 flex gap-2">
                        @foreach ([1, 2] as $n)<span class="h-14 flex-1" style="background: {{ $t['surface'] }}; border: 1px solid {{ $t['line'] }}; border-radius: {{ config('themes.radii')[$t['radius']] }}; {{ $t['card'] === 'soft' ? 'box-shadow:0 6px 16px -6px rgb(0 0 0/.25);' : '' }}{{ $t['card'] === 'outline' ? 'box-shadow:0 0 14px -2px #ffb020;' : '' }}"></span>@endforeach
                        <span class="w-1.5 rounded-full" style="background: linear-gradient(#ffb020,#ff7a3d)"></span>
                    </span>
                </div>
                <div class="space-y-3 p-4">
                    <p class="flex flex-wrap items-center gap-2 font-semibold">{{ $t['name'] }}
                        @if ($default === $key)<x-ui.badge tone="success" dot>{{ __('admin.themes.default') }}</x-ui.badge>@endif
                        <x-ui.badge>{{ $t['builtin'] ? __('admin.themes.builtin') : __('admin.themes.custom') }}</x-ui.badge>
                        @unless ($t['enabled'])<x-ui.badge tone="danger">{{ __('admin.themes.disabled') }}</x-ui.badge>@endunless
                    </p>
                    <p class="text-xs text-muted">{{ __('menu.appearance.scrollbars.'.$t['scrollbar']) }} · {{ __('menu.appearance.reveals.'.$t['reveal']) }} · {{ __('menu.appearance.cards.'.$t['card']) }}</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.themes.edit', $key) }}" class="btn btn-secondary btn-sm">{{ __('admin.themes.edit') }}</a>
                        <a href="{{ route('admin.themes.create', ['from' => $key]) }}" class="btn btn-ghost btn-sm">{{ __('admin.themes.copy') }}</a>
                        <form method="POST" action="{{ route('admin.themes.toggle', $key) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $t['enabled'] ? __('admin.themes.switch_off') : __('admin.themes.switch_on') }}</button></form>
                        @if ($default !== $key && $t['enabled'])<form method="POST" action="{{ route('admin.themes.default', $key) }}">@csrf<button class="btn btn-ghost btn-sm">{{ __('admin.themes.make_default') }}</button></form>@endif
                        <form method="POST" action="{{ route('admin.themes.destroy', $key) }}" onsubmit="return confirm(@js($t['builtin'] ? __('admin.themes.confirm_reset') : __('admin.themes.confirm_delete')))">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">{{ $t['builtin'] ? __('admin.themes.reset') : __('admin.themes.delete') }}</button></form>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</x-layouts.admin>
