<x-layouts.admin :title="__('admin.nav.announcements')">
    <div class="mb-4 flex justify-end"><a href="{{ route('admin.announcements.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('admin.announcements.new') }}</a></div>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('admin.cms.title') }}</th><th class="text-start">{{ __('admin.announcements.level') }}</th><th class="text-start">{{ __('admin.announcements.window') }}</th><th class="text-start">{{ __('admin.status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($announcements as $a)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2 font-medium">{{ $a->title }}</td>
                    <td>{{ __('admin.announcements.level_'.$a->level) }}</td>
                    <td>{{ $a->starts_at?->toDateString() ?? '…' }} → {{ $a->ends_at?->toDateString() ?? '…' }}</td>
                    <td>{{ $a->is_active ? ($a->starts_at?->isFuture() ? __('admin.cms.scheduled') : ($a->ends_at?->isPast() ? __('admin.announcements.ended') : __('admin.announcements.live'))) : __('admin.inactive') }}</td>
                    <td class="text-end"><a class="text-brand-600 hover:underline" href="{{ route('admin.announcements.edit', $a) }}">{{ __('admin.edit') }}</a>
                        <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="ms-2 text-red-600 hover:underline">{{ __('admin.delete') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $announcements->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
