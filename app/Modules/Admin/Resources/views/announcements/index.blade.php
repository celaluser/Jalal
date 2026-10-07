<x-layouts.admin :title="__('admin.nav.announcements')">
    <x-ui.page-header :title="__('admin.nav.announcements')" :description="__('admin.announcements.description')">
        <x-slot:actions><a href="{{ route('admin.announcements.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('admin.announcements.new') }}</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.table>
        <thead><tr><th>{{ __('admin.cms.title') }}</th><th>{{ __('admin.announcements.level') }}</th><th>{{ __('admin.announcements.window') }}</th><th>{{ __('admin.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($announcements as $a)
            @php($state = ! $a->is_active ? 'inactive' : ($a->starts_at?->isFuture() ? 'scheduled' : ($a->ends_at?->isPast() ? 'ended' : 'live')))
            <tr>
                <td class="font-medium">{{ $a->title }}</td>
                <td>{{ __('admin.announcements.level_'.$a->level) }}</td>
                <td class="tnum text-muted">{{ $a->starts_at?->toDateString() ?? '…' }} → {{ $a->ends_at?->toDateString() ?? '…' }}</td>
                <td><x-ui.status :value="$state" :label="['inactive' => __('admin.inactive'), 'scheduled' => __('admin.cms.scheduled'), 'ended' => __('admin.announcements.ended'), 'live' => __('admin.announcements.live')][$state]" /></td>
                <td class="whitespace-nowrap text-end">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.announcements.edit', $a) }}"><x-ui.icon name="pen" size="4" />{{ __('admin.edit') }}</a>
                    <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="5"><x-ui.empty icon="megaphone" :title="__('admin.announcements.empty_title')" :text="__('admin.announcements.empty_text')"><a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">{{ __('admin.announcements.new') }}</a></x-ui.empty></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $announcements->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
