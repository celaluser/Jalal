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
            @php($total = collect($groups)->flatten(1)->count())
            <nav class="flex min-h-0 flex-1 flex-col text-sm" aria-label="{{ __('ui.menu') }}"
                 x-data="{ q: '', all: (() => { try { return localStorage.getItem('nav.all.{{ $home }}') === '1' } catch (e) { return false } })(), closed: (() => { try { return JSON.parse(localStorage.getItem('nav.closed.{{ $home }}') || '[]') } catch (e) { return [] } })(),
                           toggle(g) { this.closed = this.closed.includes(g) ? this.closed.filter(x => x !== g) : [...this.closed, g]; try { localStorage.setItem('nav.closed.{{ $home }}', JSON.stringify(this.closed)) } catch (e) {} },
                           flip() { this.all = ! this.all; try { localStorage.setItem('nav.all.{{ $home }}', this.all ? '1' : '0') } catch (e) {} },
                           shown(g, force) { return this.q !== '' || force || ! this.closed.includes(g) },
                           match(label) { return this.q === '' || label.toLowerCase().includes(this.q.toLowerCase()) } }">
                @if ($total > 8)
                    <div class="px-3 pb-2 pt-1">
                        <label class="relative block">
                            <span class="sr-only">{{ __('ui.search') }}</span>
                            <x-ui.icon name="search" size="4" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-ink-400" />
                            <input type="search" x-model="q" placeholder="{{ __('ui.search') }}" class="w-full rounded-lg border-0 bg-white/5 py-2 pe-3 ps-9 text-sm text-white placeholder:text-ink-400 focus:bg-white/10 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </label>
                    </div>
                @endif
                <div class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-6 pt-1">
                @foreach ($groups as $group => $items)
                    @php($visible = collect($items)->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']) && (! $i['can'] || $user?->can($i['can']))))
                    @if ($visible->isNotEmpty())
                        @php($hasActive = $visible->contains(fn ($i) => request()->routeIs(...explode('|', $i['active']))))
                        @php($hasEssential = $visible->contains(fn ($i) => ! $i['advanced']) || $hasActive)
                        <div x-show="q === '' ? ({{ $hasEssential ? 'true' : 'false' }} || all) : {{ Js::from($visible->map(fn ($i) => mb_strtolower(__($i['label'])))->values()) }}.some(l => l.includes(q.toLowerCase()))">
                            <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400 transition hover:bg-white/5 hover:text-ink-200"
                                    x-on:click="toggle('{{ $group }}')" :aria-expanded="shown('{{ $group }}', {{ $hasActive ? 'true' : 'false' }})">
                                <span>{{ __($section === __('admin.panel') ? 'admin.nav.group_'.$group : 'panel.nav.group_'.$group) }}</span>
                                <x-ui.icon name="chevron-down" size="4" class="transition" x-bind:class="shown('{{ $group }}', {{ $hasActive ? 'true' : 'false' }}) ? '' : '-rotate-90 rtl:rotate-90'" />
                            </button>
                            <ul class="mt-0.5 space-y-0.5" x-show="shown('{{ $group }}', {{ $hasActive ? 'true' : 'false' }})">
                                @foreach ($visible as $item)
                                    @php($active = request()->routeIs(...explode('|', $item['active'])))
                                    <li x-show="q !== '' ? match({{ Js::from(mb_strtolower(__($item['label']))) }}) : ({{ ($item['advanced'] && ! $active) ? 'all' : 'true' }})">
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
                </div>
                @php($moreCount = collect($groups)->flatten(1)->filter(fn ($i) => $i['advanced'] && \Illuminate\Support\Facades\Route::has($i['route']) && (! $i['can'] || $user?->can($i['can'])))->count())
                @if ($moreCount > 0)
                    <div class="shrink-0 border-t border-white/10 p-3">
                        <button type="button" class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-start text-[13px] font-medium text-ink-300 transition hover:bg-white/5 hover:text-white" x-on:click="flip()" :aria-pressed="all">
                            <span x-text="all ? @js(__('ui.nav_less')) : @js(__('ui.nav_more', ['count' => $moreCount]))"></span>
                            <x-ui.icon name="chevron-down" size="4" class="transition" x-bind:class="all ? 'rotate-180' : ''" />
                        </button>
                    </div>
                @endif
            </nav>
            <div class="shrink-0 border-t border-white/10 p-4 text-xs text-ink-400">v{{ config('version.current') }}</div>
        </aside>
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink-950/60 backdrop-blur-sm {{ $tablet ? '' : 'lg:hidden' }}" x-on:click="open = false"></div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-line bg-surface/85 px-3 backdrop-blur sm:gap-3 sm:px-6">
                <button type="button" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface-2 {{ $tablet ? '' : 'lg:hidden' }}" x-on:click="open = true" aria-controls="sidebar" aria-label="{{ __('ui.menu') }}"><x-ui.icon name="menu" /></button>
                <p class="min-w-0 flex-1 truncate text-sm font-medium text-muted">{{ $title }}</p>
                <div class="hidden sm:block"><x-ui.language-switcher /></div>
                <x-ui.theme-toggle />
                <div class="relative" x-data="{ menu: false }" x-on:click.outside="menu = false" x-on:keydown.escape="menu = false">
                    <button type="button" class="flex items-center gap-2 rounded-xl p-1 pe-2 transition hover:bg-surface-2" x-on:click="menu = !menu" aria-haspopup="menu" :aria-expanded="menu">
                        <x-ui.avatar :name="$user->name" size="8" />
                        <span class="hidden max-w-[10rem] truncate text-sm font-medium sm:block">{{ $user->name }}</span>
                        <x-ui.icon name="chevron-down" size="4" class="text-muted" />
                    </button>
                    <div x-show="menu" x-cloak x-transition.origin.top.right role="menu" class="absolute end-0 mt-2 w-60 overflow-hidden rounded-xl border border-line bg-surface shadow-pop">
                        <div class="border-b border-line px-4 py-3"><p class="truncate text-sm font-semibold">{{ $user->name }}</p><p class="truncate text-xs text-muted">{{ $user->email }}</p></div>
                        {{-- The header has no room for the language buttons on a phone, so they live here. --}}
                        <div class="border-b border-line px-3 py-2 sm:hidden"><x-ui.language-switcher /></div>
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
