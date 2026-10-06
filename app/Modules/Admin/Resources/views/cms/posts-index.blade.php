<x-layouts.admin :title="__('admin.nav.blog')">
    <div class="mb-4 flex justify-end"><a href="{{ route('admin.posts.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('admin.cms.new_post') }}</a></div>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('admin.cms.title') }}</th><th class="text-start">{{ __('admin.cms.language') }}</th><th class="text-start">{{ __('admin.status') }}</th><th class="text-start">{{ __('admin.cms.publish_date') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($posts as $post)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2 font-medium">{{ $post->title }}</td><td class="uppercase">{{ $post->locale }}</td>
                    <td>{{ $post->is_published ? ($post->published_at?->isFuture() ? __('admin.cms.scheduled') : __('admin.cms.published')) : __('admin.cms.draft') }}</td>
                    <td>{{ $post->published_at?->toDateString() ?? '—' }}</td>
                    <td class="text-end"><a class="text-brand-600 hover:underline" href="{{ route('admin.posts.edit', $post) }}">{{ __('admin.edit') }}</a>
                        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="ms-2 text-red-600 hover:underline">{{ __('admin.delete') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $posts->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
