<x-layouts.admin :title="__('admin.nav.pages')">
    <div class="mb-4 flex justify-end"><a href="{{ route('admin.pages.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('admin.cms.new_page') }}</a></div>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('admin.cms.title') }}</th><th class="text-start">{{ __('admin.cms.slug') }}</th><th class="text-start">{{ __('admin.cms.language') }}</th><th class="text-start">{{ __('admin.status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($pages as $page)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2 font-medium">{{ $page->title }}</td><td>/p/{{ $page->slug }}</td><td class="uppercase">{{ $page->locale }}</td>
                    <td>{{ $page->is_published ? __('admin.cms.published') : __('admin.cms.draft') }}@if ($page->in_footer) · {{ __('admin.cms.footer') }}@endif</td>
                    <td class="text-end"><a class="text-brand-600 hover:underline" href="{{ route('admin.pages.edit', $page) }}">{{ __('admin.edit') }}</a>
                        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="ms-2 text-red-600 hover:underline">{{ __('admin.delete') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.admin>
