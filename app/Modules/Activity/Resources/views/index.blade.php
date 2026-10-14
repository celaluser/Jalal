<x-layouts.app :title="__('activity.title')">
    <x-ui.page-header :title="__('activity.title')" :description="__('activity.subtitle')" />

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="search" name="q" value="{{ $q }}" class="field min-w-56 flex-1" placeholder="{{ __('activity.search') }}" aria-label="{{ __('activity.search') }}">
        <select name="subject" class="field !w-auto" onchange="this.form.submit()" aria-label="{{ __('activity.what') }}">
            <option value="">{{ __('activity.all') }}</option>
            @foreach ($subjects as $s)<option value="{{ $s }}" @selected($subject === $s)>{{ $s }}</option>@endforeach
        </select>
        <button class="btn btn-secondary">{{ __('admin.search') }}</button>
    </form>

    @if ($logs->isEmpty())
        <div class="card"><x-ui.empty icon="file-text" :title="__('activity.empty')" /></div>
    @else
        <x-ui.table>
            <thead><tr><th>{{ __('activity.when') }}</th><th>{{ __('activity.who') }}</th><th>{{ __('activity.what') }}</th><th>{{ __('activity.details') }}</th></tr></thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-muted" title="{{ $log->created_at->toDayDateTimeString() }}">{{ $log->created_at->diffForHumans() }}</td>
                        <td>{{ $log->user_name ?: __('activity.system') }}</td>
                        <td><x-ui.badge :tone="['created' => 'success', 'deleted' => 'danger', 'updated' => 'info'][$log->event] ?? 'neutral'">{{ __('activity.event_'.$log->event) === 'activity.event_'.$log->event ? $log->event : __('activity.event_'.$log->event) }}</x-ui.badge>
                            <span class="ms-1 font-medium">{{ $log->subject_type }}</span> <span class="text-muted">{{ $log->label }}</span></td>
                        <td class="max-w-md text-sm text-muted">
                            @foreach (($log->changes ?? []) as $field => $pair)
                                @if (is_array($pair) && count($pair) === 2)<span class="me-2 inline-block"><code>{{ $field }}</code>: <bdi>{{ is_scalar($pair[0]) || $pair[0] === null ? ($pair[0] ?? '—') : '…' }}</bdi> → <bdi>{{ is_scalar($pair[1]) || $pair[1] === null ? ($pair[1] ?? '—') : '…' }}</bdi></span>@endif
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <x-slot:footer>@if ($logs->hasPages()){{ $logs->links() }}@endif</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
