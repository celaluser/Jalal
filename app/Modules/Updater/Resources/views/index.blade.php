<x-layouts.admin :title="__('updater.title')">
    <x-ui.page-header :title="__('updater.title')" :description="__('updater.current_version', ['version' => $current])" />
    <div class="grid gap-5 lg:grid-cols-5">
        <x-ui.card :title="__('updater.upload')" :description="__('updater.upload_help')" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.updates.upload') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ name: null }">
                @csrf
                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-line-strong px-4 py-8 text-center transition hover:border-accent-500 hover:bg-surface-2/50">
                    <x-ui.icon name="download" size="6" class="rotate-180 text-muted" />
                    <span class="text-sm font-medium" x-text="name ?? @js(__('updater.choose_file'))"></span>
                    <span class="text-xs text-muted">.zip</span>
                    <input type="file" name="package" accept=".zip" required class="sr-only" x-on:change="name = $event.target.files[0]?.name">
                </label>
                @error('package')<p class="flex items-center gap-1 text-sm text-red-600" role="alert"><x-ui.icon name="alert" size="4" />{{ $message }}</p>@enderror
                <x-ui.button icon="check">{{ __('updater.check_package') }}</x-ui.button>
            </form>
        </x-ui.card>
        <x-ui.card :title="__('updater.history')" :pad="false" class="lg:col-span-3">
            <ul class="divide-y divide-line text-sm">
                @forelse ($history as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6"><span class="tnum font-medium">{{ $row->from_version }} → {{ $row->version }}</span><x-ui.status :value="$row->status" /><span class="text-xs text-muted">{{ $row->created_at->toDayDateTimeString() }}</span></li>
                @empty
                    <li><x-ui.empty icon="refresh" :title="__('updater.no_history')" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</x-layouts.admin>
