<x-layouts.admin :title="__('updater.title')">
    <x-ui.page-header :title="__('updater.review')" :back="['url' => route('admin.updates.index'), 'label' => __('updater.title')]" />
    <div class="max-w-xl space-y-5">
        <x-ui.card>
            <div class="flex items-center gap-4">
                <span class="grid size-12 place-items-center rounded-2xl bg-brand-100 text-brand-800 dark:bg-brand-900/40 dark:text-brand-200"><x-ui.icon name="refresh" size="6" /></span>
                <div><p class="display tnum text-2xl font-semibold">{{ $current }} → {{ $package->version() }}</p><p class="text-sm text-muted">{{ __('updater.file_count', ['count' => count($package->paths())]) }}</p></div>
            </div>
            @if ($package->notes())<pre class="mt-4 whitespace-pre-wrap rounded-xl bg-surface-2 p-4 font-sans text-sm">{{ $package->notes() }}</pre>@endif
        </x-ui.card>
        <x-ui.alert type="warning">{{ __('updater.backup_warning') }}</x-ui.alert>
        <form method="POST" action="{{ route('admin.updates.apply') }}" class="space-y-4">
            @csrf
            <x-ui.input name="password" type="password" :label="__('updater.confirm_password')" required autofocus />
            <div class="flex gap-3"><x-ui.button :block="false" size="lg">{{ __('updater.apply') }}</x-ui.button></div>
        </form>
        <form method="POST" action="{{ route('admin.updates.cancel') }}">@csrf @method('DELETE')<x-ui.button variant="ghost" :block="false">{{ __('updater.cancel') }}</x-ui.button></form>
    </div>
</x-layouts.admin>
