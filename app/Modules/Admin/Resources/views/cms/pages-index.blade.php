<x-layouts.admin :title="__('admin.nav.pages')">
    <x-ui.page-header :title="__('admin.nav.pages')" :description="__('admin.cms.pages_description')">
        <x-slot:actions><a href="{{ route('admin.pages.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('admin.cms.new_page') }}</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.table>
        <thead><tr><th>{{ __('admin.cms.title') }}</th><th>{{ __('admin.cms.slug') }}</th><th>{{ __('admin.cms.language') }}</th><th>{{ __('admin.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($pages as $page)
            <tr>
                <td class="font-medium">{{ $page->title }}</td>
                <td class="font-mono text-xs text-muted">/p/{{ $page->slug }}</td>
                <td><x-ui.badge>{{ strtoupper($page->locale) }}</x-ui.badge></td>
                <td><x-ui.status :value="$page->is_published ? 'published' : 'draft'" :label="$page->is_published ? __('admin.cms.published') : __('admin.cms.draft')" />@if ($page->in_footer)<span class="ms-1 text-xs text-muted">{{ __('admin.cms.footer') }}</span>@endif</td>
                <td class="whitespace-nowrap text-end">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.pages.edit', $page) }}"><x-ui.icon name="pen" size="4" />{{ __('admin.edit') }}</a>
                    <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="5"><x-ui.empty icon="file-text" :title="__('admin.cms.pages_empty')" :text="__('admin.cms.pages_empty_text')"><a href="{{ route('admin.pages.create') }}" class="btn btn-primary">{{ __('admin.cms.new_page') }}</a></x-ui.empty></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
</x-layouts.admin>
