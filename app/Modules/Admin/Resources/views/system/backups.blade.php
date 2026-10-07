<x-layouts.admin :title="__('admin.nav.backups')">
    <x-ui.page-header :title="__('admin.nav.backups')" :description="__('admin.system.backups_description')">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.system.backups.store') }}">@csrf
                <button class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('admin.system.backup_now') }}</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert type="warning" class="mb-4">{{ __('admin.system.backup_warning') }}</x-ui.alert>

    <x-ui.table>
        <thead><tr><th>{{ __('admin.system.backup_name') }}</th><th>{{ __('admin.system.backup_size') }}</th><th>{{ __('admin.system.backup_date') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($backups as $b)
            <tr>
                <td class="font-medium" dir="ltr"><bdi>{{ $b['name'] }}</bdi></td>
                <td class="tnum text-muted">{{ number_format($b['size'] / 1024, 1) }} KB</td>
                <td class="tnum text-muted">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['created'])->toDayDateTimeString() }}</td>
                <td class="whitespace-nowrap text-end">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.system.backups.download', $b['name']) }}"><x-ui.icon name="download" size="4" />{{ __('admin.system.download') }}</a>
                    <form method="POST" action="{{ route('admin.system.backups.destroy', $b['name']) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="4"><x-ui.empty icon="download" :title="__('admin.system.backups_empty_title')" :text="__('admin.system.backups_empty_text')" /></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
</x-layouts.admin>
