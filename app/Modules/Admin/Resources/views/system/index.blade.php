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

    @if (session('tool_output'))<pre class="mt-6 overflow-x-auto rounded-lg bg-surface-2 p-3 text-xs" dir="ltr">{{ session('tool_output') }}</pre>@endif
    @error('system')<x-ui.alert type="danger" class="mt-6">{{ $message }}</x-ui.alert>@enderror

    {{-- No terminal, no cron? Everything below works from the browser. --}}
    <section class="card card-pad mt-6">
        <h2 class="display text-lg font-semibold">{{ __('admin.system.webcron') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('admin.system.webcron_help') }}</p>
        <div class="mt-4 flex flex-wrap items-center gap-2"><code class="min-w-0 flex-1 break-all rounded-lg bg-surface-2 p-2.5 text-sm" dir="ltr">{{ $cronUrl }}</code></div>
        <p class="mt-2 text-xs text-muted">{{ __('admin.system.webcron_example') }} <code dir="ltr" class="break-all">* * * * * wget -q -O /dev/null {{ $cronUrl }}</code></p>
        <form method="POST" action="{{ route('admin.system.cron') }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf @method('PUT')
            <div><label for="mode" class="mb-1.5 block text-sm font-medium">{{ __('admin.system.webcron_mode') }}</label>
                <select id="mode" name="mode" class="field">@foreach (['auto', 'always', 'off'] as $m)<option value="{{ $m }}" @selected($cronMode === $m)>{{ __('admin.system.webcron_'.$m) }}</option>@endforeach</select></div>
            <button class="btn btn-secondary btn-sm">{{ __('admin.save') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.system.cron') }}" class="mt-3" onsubmit="return confirm('{{ __('admin.system.webcron_regen_confirm') }}')">@csrf @method('PUT')<input type="hidden" name="regenerate" value="1"><button class="btn btn-ghost btn-sm">{{ __('admin.system.webcron_regen') }}</button></form>
    </section>

    <section class="card card-pad mt-6">
        <h2 class="display text-lg font-semibold">{{ __('admin.system.tools') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('admin.system.tools_help') }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($tools as $tool)
                <form method="POST" action="{{ route('admin.system.tool', $tool) }}" @if (in_array($tool, ['flush_failed'])) onsubmit="return confirm('{{ __('admin.system.tool_confirm') }}')" @endif>@csrf
                    <button class="btn btn-secondary btn-sm"><x-ui.icon name="refresh" size="4" />{{ __('admin.system.tool_'.$tool) }}</button></form>
            @endforeach
        </div>
    </section>

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
