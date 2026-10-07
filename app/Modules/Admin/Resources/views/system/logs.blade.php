<x-layouts.admin :title="__('admin.nav.logs')">
    <x-ui.page-header :title="__('admin.nav.logs')" :description="__('admin.system.logs_description')">
        @if ($file !== '')
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.system.logs.clear') }}" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')
                    <input type="hidden" name="file" value="{{ $file }}">
                    <button class="btn btn-secondary"><x-ui.icon name="trash" size="4" />{{ __('admin.system.clear_log') }}</button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if (count($files) === 0)
        <div class="card"><x-ui.empty icon="file-text" :title="__('admin.system.no_files_title')" :text="__('admin.system.no_files_text')" /></div>
    @else
        <form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-4">
            <div><label class="mb-1 block text-xs font-medium text-muted" for="file">{{ __('admin.system.log_file') }}</label>
                <select id="file" name="file" class="field" onchange="this.form.submit()">@foreach ($files as $f)<option value="{{ $f['name'] }}" @selected($f['name'] === $file)>{{ $f['name'] }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-xs font-medium text-muted" for="level">{{ __('admin.system.level') }}</label>
                <select id="level" name="level" class="field"><option value="">{{ __('admin.all') }}</option>@foreach ($levels as $l)<option value="{{ $l }}" @selected($l === $level)>{{ $l }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium text-muted" for="q">{{ __('admin.system.search') }}</label>
                <div class="flex gap-2"><input id="q" name="q" value="{{ $search }}" class="field" maxlength="100"><button class="btn btn-dark">{{ __('admin.filter') }}</button></div></div>
        </form>

        <div class="space-y-2">
            @forelse ($entries as $e)
                @php($tone = in_array($e['level'], ['emergency', 'alert', 'critical', 'error']) ? 'danger' : (in_array($e['level'], ['warning', 'notice']) ? 'warning' : 'neutral'))
                <details class="card group">
                    <summary class="flex cursor-pointer list-none items-start gap-3 px-4 py-3">
                        <x-ui.badge :tone="$tone">{{ $e['level'] }}</x-ui.badge>
                        <span class="min-w-0 flex-1 break-words text-sm" dir="ltr">{{ \Illuminate\Support\Str::limit($e['message'], 240) }}</span>
                        <time class="tnum shrink-0 text-xs text-muted" dir="ltr">{{ $e['time'] }}</time>
                    </summary>
                    <div class="border-t border-line px-4 py-3">
                        <p class="break-words text-sm" dir="ltr">{{ $e['message'] }}</p>
                        @if ($e['trace'] !== '')
                            <p class="eyebrow mt-3">{{ __('admin.system.trace') }}</p>
                            <pre class="mt-1 max-h-72 overflow-auto rounded-xl bg-ink-950 p-3 text-xs text-ink-200" dir="ltr">{{ \Illuminate\Support\Str::limit($e['trace'], 6000) }}</pre>
                        @endif
                    </div>
                </details>
            @empty
                <div class="card"><x-ui.empty icon="check-circle" :title="__('admin.system.log_empty_title')" :text="__('admin.system.log_empty_text')" /></div>
            @endforelse
        </div>
    @endif
</x-layouts.admin>
