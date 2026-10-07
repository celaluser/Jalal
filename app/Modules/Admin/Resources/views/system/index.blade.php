<x-layouts.admin :title="__('admin.nav.system_status')">
    <x-ui.page-header :title="__('admin.nav.system_status')" :description="__('admin.system.description')" />

    {{-- Background work: the two things shared hosting most often gets wrong --}}
    <section class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="{{ __('admin.system.workers') }}">
        @foreach (['scheduler', 'queue'] as $worker)
            @php($w = $workers[$worker])
            <div class="card p-5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium text-muted">{{ __('admin.system.'.$worker) }}</p>
                    <x-ui.badge class="shrink-0 whitespace-nowrap" :tone="['ok' => 'success', 'stale' => 'warning', 'never' => 'danger'][$w['state']]" dot>{{ __('admin.system.state_'.$w['state']) }}</x-ui.badge>
                </div>
                <p class="mt-3 text-sm {{ $w['state'] === 'ok' ? 'text-muted' : '' }}">
                    {{ $w['seconds'] === null ? __('admin.system.never_seen') : __('admin.system.ago', ['seconds' => $w['seconds']]) }}
                </p>
                @if ($w['state'] !== 'ok')<p class="mt-2 text-xs text-muted">{{ __('admin.system.'.$worker.'_help') }}</p>@endif
            </div>
        @endforeach
        <x-ui.stat :label="__('admin.system.pending')" :value="$workers['pending'] ?? '—'" icon="clock" />
        <x-ui.stat :label="__('admin.system.failed')" :value="$workers['failed'] ?? '—'" icon="alert" />
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card card-pad">
            <h2 class="display text-lg font-semibold">{{ __('admin.system.environment') }}</h2>
            <dl class="mt-4 divide-y divide-line text-sm">
                @foreach ($environment as $key => $value)
                    <div class="flex items-center justify-between gap-4 py-2.5"><dt class="text-muted">{{ __('admin.system.env.'.$key) }}</dt><dd class="text-end font-medium" dir="ltr"><bdi>{{ $value }}</bdi></dd></div>
                @endforeach
            </dl>
        </section>

        <div class="space-y-6">
            <section class="card card-pad">
                <h2 class="display text-lg font-semibold">{{ __('admin.system.limits') }}</h2>
                <dl class="mt-4 divide-y divide-line text-sm">
                    @foreach ($limits as $key => $value)
                        <div class="flex items-center justify-between gap-4 py-2.5"><dt class="text-muted">{{ __('admin.system.limit.'.$key) }}</dt><dd class="font-medium" dir="ltr"><bdi>{{ $value }}</bdi></dd></div>
                    @endforeach
                </dl>
            </section>

            <section class="card card-pad">
                <h2 class="display text-lg font-semibold">{{ __('admin.system.checks') }}</h2>
                <ul class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach ($checks as $check)
                        <li class="flex items-center gap-2">
                            <span class="grid size-5 shrink-0 place-items-center rounded-full {{ $check['ok'] ? 'bg-accent-100 text-accent-800 dark:bg-accent-900/50 dark:text-accent-200' : 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300' }}"><x-ui.icon :name="$check['ok'] ? 'check' : 'x'" size="3" /></span>
                            <span dir="ltr"><bdi>{{ $check['label'] }}</bdi></span>
                            <span class="sr-only">{{ $check['ok'] ? __('admin.system.state_ok') : __('admin.system.state_never') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>

    <section class="card card-pad mt-6">
        <h2 class="display text-lg font-semibold">{{ __('admin.system.maintenance') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('admin.system.maintenance_help') }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($actions as $action)
                <form method="POST" action="{{ route('admin.system.clear', $action) }}">@csrf
                    <button class="btn {{ $action === 'all' ? 'btn-dark' : 'btn-secondary' }} btn-sm"><x-ui.icon name="refresh" size="4" />{{ __('admin.system.clear_'.$action) }}</button>
                </form>
            @endforeach
        </div>
    </section>
</x-layouts.admin>
