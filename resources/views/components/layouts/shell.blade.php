@props(['groups', 'title' => null, 'section', 'home', 'announcements' => false])
@php($user = auth()->user())
@php($tablet = (bool) session(\App\Modules\Orders\Http\Controllers\TabletModeController::KEY, false))
@if ($user->restaurant_id)
    @push('head')<link rel="manifest" href="{{ route('staff.manifest') }}"><meta name="theme-color" content="{{ $user->restaurant?->brandColor() }}">@endpush
@endif
<x-layouts.base :title="$title" :tablet="$tablet">
    <div class="flex min-h-screen" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
        {{-- Sidebar: ink in both themes, saffron marks where you are --}}
        <aside id="sidebar" class="fixed inset-y-0 start-0 z-40 flex w-64 -translate-x-full flex-col bg-ink-950 text-ink-200 transition-transform duration-200 ease-out rtl:translate-x-full {{ $tablet ? '' : 'lg:static lg:translate-x-0 lg:rtl:translate-x-0' }}"
               :class="open && '!translate-x-0'" aria-label="{{ __('ui.menu') }}">
            <div class="flex h-16 shrink-0 items-center justify-between px-5">
                <a href="{{ route($home) }}" class="flex items-center gap-2.5">
                    <x-ui.qr-mark size="8" />
                    <span class="leading-tight">
                        <span class="display block text-[15px] font-semibold text-white">{{ config('app.name') }}</span>
                        <span class="block text-[11px] font-medium uppercase tracking-[0.14em] text-ink-400">{{ $section }}</span>
                    </span>
                </a>
                <button type="button" class="grid size-8 place-items-center rounded-lg text-ink-400 hover:bg-white/10 lg:hidden" x-on:click="open = false" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" /></button>
            </div>
            <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6 pt-2 text-sm">
                @foreach ($groups as $group => $items)
                    @php($visible = collect($items)->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']) && (! $i['can'] || $user?->can($i['can']))))
                    @if ($visible->isNotEmpty())
                        <div>
                            <p class="mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-500">{{ __($section === __('admin.panel') ? 'admin.nav.group_'.$group : 'panel.nav.group_'.$group) }}</p>
                            <ul class="space-y-0.5">
                                @foreach ($visible as $item)
                                    @php($active = request()->routeIs(...explode('|', $item['active'])))
                                    <li>
                                        <a href="{{ route($item['route'], $item['params']) }}" @if ($active) aria-current="page" @endif
                                           @class(['group relative flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition', 'bg-white/10 text-white' => $active, 'text-ink-300 hover:bg-white/5 hover:text-white' => ! $active])>
                                            @if ($active)<span class="absolute inset-y-1.5 start-0 w-[3px] rounded-full bg-brand-500"></span>@endif
                                            @if ($item['icon'])<x-ui.icon :name="$item['icon']" size="5" :class="$active ? 'text-brand-400' : 'text-ink-400 group-hover:text-ink-200'" />@endif
                                            <span class="truncate">{{ __($item['label']) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </nav>
            <div class="shrink-0 border-t border-white/10 p-4 text-xs text-ink-500">v{{ config('version.current') }}</div>
        </aside>
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink-950/60 backdrop-blur-sm {{ $tablet ? '' : 'lg:hidden' }}" x-on:click="open = false"></div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface/85 px-4 backdrop-blur sm:px-6">
                <button type="button" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface-2 {{ $tablet ? '' : 'lg:hidden' }}" x-on:click="open = true" aria-controls="sidebar" aria-label="{{ __('ui.menu') }}"><x-ui.icon name="menu" /></button>
                <p class="min-w-0 flex-1 truncate text-sm font-medium text-muted">{{ $title }}</p>
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
                <div class="relative" x-data="{ menu: false }" x-on:click.outside="menu = false" x-on:keydown.escape="menu = false">
                    <button type="button" class="flex items-center gap-2 rounded-xl p-1 pe-2 transition hover:bg-surface-2" x-on:click="menu = !menu" aria-haspopup="menu" :aria-expanded="menu">
                        <x-ui.avatar :name="$user->name" size="8" />
                        <span class="hidden max-w-[10rem] truncate text-sm font-medium sm:block">{{ $user->name }}</span>
                        <x-ui.icon name="chevron-down" size="4" class="text-muted" />
                    </button>
                    <div x-show="menu" x-cloak x-transition.origin.top.right role="menu" class="absolute end-0 mt-2 w-60 overflow-hidden rounded-xl border border-line bg-surface shadow-pop">
                        <div class="border-b border-line px-4 py-3"><p class="truncate text-sm font-semibold">{{ $user->name }}</p><p class="truncate text-xs text-muted">{{ $user->email }}</p></div>
                        <div class="p-1.5 text-sm">
                            @if ($user->restaurant_id)
                                <form method="POST" action="{{ route('tablet.toggle') }}">@csrf<button role="menuitem" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-start hover:bg-surface-2"><x-ui.icon name="layout" size="4" class="text-muted" />{{ $tablet ? __('ui.tablet_off') : __('ui.tablet_on') }}</button></form>
                            @endif
                            <a role="menuitem" href="{{ route('two-factor.show') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 hover:bg-surface-2"><x-ui.icon name="shield" size="4" class="text-muted" />{{ __('ui.security') }}</a>
                            <a role="menuitem" href="{{ route('home') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 hover:bg-surface-2"><x-ui.icon name="external" size="4" class="text-muted" />{{ __('ui.website') }}</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button role="menuitem" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-start hover:bg-surface-2"><x-ui.icon name="log-out" size="4" class="text-muted" />{{ __('ui.logout') }}</button></form>
                        </div>
                    </div>
                </div>
            </header>
            <main class="rise mx-auto w-full max-w-7xl min-w-0 flex-1 space-y-5 p-4 sm:p-6 lg:p-8">
                @if ($announcements)<x-announcements />@endif
                @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
                @if ($errors->any() && ! $errors->has('demo'))
                    <x-ui.alert type="error"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
