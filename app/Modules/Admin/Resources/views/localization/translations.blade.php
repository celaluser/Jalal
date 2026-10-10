<x-layouts.admin :title="__('admin.nav.translations')">
    <x-ui.page-header :title="__('admin.nav.translations')" :description="__('admin.localization.translations_sub')" />

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <select name="locale" class="field !w-auto" onchange="this.form.submit()" aria-label="{{ __('admin.localization.language') }}">@foreach ($languages as $l)<option value="{{ $l->code }}" @selected($locale === $l->code)>{{ $l->name }} ({{ $l->code }})</option>@endforeach</select>
        <select name="group" class="field !w-auto" onchange="this.form.submit()" aria-label="{{ __('admin.localization.file') }}">@foreach ($groups as $g)<option value="{{ $g }}" @selected($group === $g)>{{ $g }}</option>@endforeach</select>
        <input type="search" name="q" value="{{ $q }}" class="field min-w-48 flex-1" placeholder="{{ __('admin.localization.search_texts') }}" aria-label="{{ __('admin.localization.search_texts') }}">
        <button class="btn btn-secondary">{{ __('admin.search') }}</button>
    </form>

    <form method="POST" action="{{ route('admin.translations.update') }}">
        @csrf @method('PUT')
        <input type="hidden" name="locale" value="{{ $locale }}"><input type="hidden" name="group" value="{{ $group }}">
        <div class="card divide-y divide-line">
            @forelse ($rows as $row)
                <div class="grid gap-2 p-4 lg:grid-cols-[1fr_1fr]">
                    <div class="min-w-0"><p class="truncate font-mono text-xs text-muted" dir="ltr">{{ $row['key'] }}</p><p class="mt-1 text-sm">{{ $row['english'] }}</p></div>
                    <div><textarea name="rows[{{ $row['key'] }}]" rows="{{ strlen($row['english']) > 90 ? 3 : 1 }}" class="field {{ $row['overridden'] ? 'ring-1 ring-brand-400' : '' }}" aria-label="{{ $row['key'] }}">{{ $row['value'] }}</textarea>
                        @if ($row['overridden'])<p class="mt-1 text-xs text-muted">{{ __('admin.localization.overridden') }}</p>@endif</div>
                </div>
            @empty
                <p class="p-8 text-center text-sm text-muted">{{ __('admin.localization.no_texts') }}</p>
            @endforelse
        </div>
        @if ($rows->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
                <p class="flex items-center gap-2 text-sm text-muted">{{ __('admin.localization.page_of', ['page' => $page, 'pages' => max(1, $pages), 'total' => $total]) }}
                    @if ($page > 1)<a class="btn btn-ghost btn-sm" href="{{ route('admin.translations.index', ['locale' => $locale, 'group' => $group, 'q' => $q, 'page' => $page - 1]) }}">‹</a>@endif
                    @if ($page < $pages)<a class="btn btn-ghost btn-sm" href="{{ route('admin.translations.index', ['locale' => $locale, 'group' => $group, 'q' => $q, 'page' => $page + 1]) }}">›</a>@endif</p>
            </div>
        @endif
    </form>
    <form method="POST" action="{{ route('admin.translations.reset') }}" class="mt-6" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')
        <input type="hidden" name="locale" value="{{ $locale }}"><input type="hidden" name="group" value="{{ $group }}">
        <button class="text-sm text-red-600 underline dark:text-red-400">{{ __('admin.localization.reset_file') }}</button></form>
</x-layouts.admin>
