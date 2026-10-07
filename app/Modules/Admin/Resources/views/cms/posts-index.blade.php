<x-layouts.admin :title="__('admin.nav.blog')">
    <x-ui.page-header :title="__('admin.nav.blog')" :description="__('admin.cms.blog_description')">
        <x-slot:actions><a href="{{ route('admin.posts.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('admin.cms.new_post') }}</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.table>
        <thead><tr><th>{{ __('admin.cms.title') }}</th><th>{{ __('admin.cms.language') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.cms.publish_date') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($posts as $post)
            @php($state = ! $post->is_published ? 'draft' : ($post->published_at?->isFuture() ? 'scheduled' : 'published'))
            <tr>
                <td><div class="flex items-center gap-3">@if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" class="size-10 rounded-lg object-cover">@else<span class="grid size-10 place-items-center rounded-lg bg-surface-2 text-muted"><x-ui.icon name="image" size="5" /></span>@endif<span class="font-medium">{{ $post->title }}</span></div></td>
                <td><x-ui.badge>{{ strtoupper($post->locale) }}</x-ui.badge></td>
                <td><x-ui.status :value="$state" :label="__('admin.cms.'.$state)" /></td>
                <td class="tnum text-muted">{{ $post->published_at?->toDateString() ?? '—' }}</td>
                <td class="whitespace-nowrap text-end">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.posts.edit', $post) }}"><x-ui.icon name="pen" size="4" />{{ __('admin.edit') }}</a>
                    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="5"><x-ui.empty icon="pen" :title="__('admin.cms.posts_empty')" :text="__('admin.cms.posts_empty_text')"><a href="{{ route('admin.posts.create') }}" class="btn btn-primary">{{ __('admin.cms.new_post') }}</a></x-ui.empty></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $posts->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
