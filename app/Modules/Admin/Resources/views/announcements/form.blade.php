<x-layouts.admin :title="$announcement->exists ? $announcement->title : __('admin.announcements.new')">
    <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" class="mx-auto max-w-2xl">
        @csrf @if ($announcement->exists) @method('PUT') @endif
        <x-ui.card class="space-y-4">
            <x-ui.input name="title" :label="__('admin.cms.title')" :value="old('title', $announcement->title)" required />
            <div>
                <label for="body" class="mb-1 block text-sm font-medium">{{ __('admin.announcements.body') }}</label>
                <textarea id="body" name="body" rows="4" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('body', $announcement->body) }}</textarea>
                @error('body')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.cms.markdown_help') }}</p>
            </div>
            <x-ui.select name="level" :label="__('admin.announcements.level')" :value="$announcement->level" :options="collect(\App\Modules\Support\Models\Announcement::LEVELS)->mapWithKeys(fn ($l) => [$l => __('admin.announcements.level_'.$l)])->all()" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="starts_at" type="datetime-local" :label="__('admin.announcements.starts')" :value="old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i'))" />
                <x-ui.input name="ends_at" type="datetime-local" :label="__('admin.announcements.ends')" :value="old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i'))" />
            </div>
            <div class="flex gap-6">
                <x-ui.checkbox name="is_active" :label="__('admin.active')" :checked="$announcement->is_active" />
                <x-ui.checkbox name="is_dismissible" :label="__('admin.announcements.dismissible')" :checked="$announcement->is_dismissible" />
            </div>
            <x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.admin>
