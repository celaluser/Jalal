{{-- Days and hours a menu item, category or menu is shown. Fields: schedule[days][], schedule[from], schedule[to]. --}}
@php
    $days = $schedule['days'] ?? [];
    $names = [__('analytics.mon'), __('analytics.tue'), __('analytics.wed'), __('analytics.thu'), __('analytics.fri'), __('analytics.sat'), __('analytics.sun')];
@endphp
<fieldset class="space-y-3">
    <legend class="text-sm font-medium">{{ $label ?? __('menu.schedule') }}</legend>
    <p class="text-xs text-muted">{{ __('menu.schedule_help') }}</p>
    <div class="flex flex-wrap gap-2">
        @foreach ($names as $i => $name)
            <label class="cursor-pointer"><input type="checkbox" name="schedule[days][]" value="{{ $i + 1 }}" class="peer sr-only" @checked(in_array($i + 1, old('schedule.days', $days)))>
                <span class="inline-flex min-w-11 justify-center rounded-lg border border-line-strong px-2.5 py-1.5 text-sm transition peer-checked:border-accent-600 peer-checked:bg-accent-50 peer-checked:font-medium peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500 dark:peer-checked:bg-accent-900/20">{{ $name }}</span></label>
        @endforeach
    </div>
    <div class="flex items-center gap-2">
        <input type="time" name="schedule[from]" value="{{ old('schedule.from', $schedule['from'] ?? '') }}" class="field !w-auto" aria-label="{{ __('menu.schedule_from') }}">
        <span class="text-muted">–</span>
        <input type="time" name="schedule[to]" value="{{ old('schedule.to', $schedule['to'] ?? '') }}" class="field !w-auto" aria-label="{{ __('menu.schedule_to') }}">
    </div>
</fieldset>
