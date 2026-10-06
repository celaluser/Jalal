<x-layouts.base :title="$title ?? null">
    <div class="flex min-h-screen" x-data="{ open: false }" x-init="$watch('open', v => document.body.classList.toggle('overflow-hidden', v))">
        <aside class="fixed inset-y-0 start-0 z-30 w-60 -translate-x-full border-e border-gray-200 bg-white transition-transform dark:border-gray-800 dark:bg-gray-900 rtl:translate-x-full lg:static lg:translate-x-0 lg:rtl:translate-x-0"
               :class="open && '!translate-x-0'">
            <div class="flex h-14 items-center px-4 font-bold text-brand-600">{{ config('app.name') }}</div>
            <nav class="space-y-4 px-3 pb-6 text-sm" aria-label="{{ __('admin.nav.label') }}">
                @foreach (\App\Modules\Admin\Support\AdminNav::groups() as $group => $items)
                    <div>
                        <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.nav.group_'.$group) }}</p>
                        @foreach ($items as $item)
                            @if (\Illuminate\Support\Facades\Route::has($item['route']))
                                <a href="{{ route($item['route']) }}" @class(['block rounded-lg px-2 py-1.5', 'bg-brand-50 font-semibold text-brand-700 dark:bg-gray-800 dark:text-brand-500' => request()->routeIs($item['active']), 'hover:bg-gray-100 dark:hover:bg-gray-800' => ! request()->routeIs($item['active'])])
                                   @if (request()->routeIs($item['active'])) aria-current="page" @endif>{{ __($item['label']) }}</a>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </aside>
        <div x-show="open" x-cloak class="fixed inset-0 z-20 bg-black/40 lg:hidden" x-on:click="open = false"></div>
        <div class="flex min-w-0 flex-1 flex-col" x-data="themeToggle">
            <header class="flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 dark:border-gray-800 dark:bg-gray-900">
                <button type="button" class="lg:hidden" x-on:click="open = true" aria-label="{{ __('admin.nav.open_menu') }}">☰</button>
                <span class="hidden text-sm text-gray-500 lg:block">{{ $title ?? '' }}</span>
                <div class="flex items-center gap-4 text-sm">
                    <x-ui.language-switcher />
                    <button type="button" x-on:click="toggle()" class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">{{ __('ui.toggle_theme') }}</button>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">{{ __('ui.logout') }}</button></form>
                </div>
            </header>
            <main class="min-w-0 flex-1 p-4 sm:p-6">
                @if (session('status'))<x-ui.alert>{{ session('status') }}</x-ui.alert>@endif
                @if ($errors->any() && ! $errors->has('demo'))
                    <x-ui.alert type="error"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
