<x-layouts.admin :title="$announcement->exists ? $announcement->title : __('admin.announcements.new')">
    <x-ui.page-header :title="$announcement->exists ? $announcement->title : __('admin.announcements.new')" :back="['url' => route('admin.announcements.index'), 'label' => __('admin.nav.announcements')]" />
    <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" class="max-w-2xl space-y-5">
        @csrf @if ($announcement->exists) @method('PUT') @endif
        <x-ui.card class="space-y-4">
            <x-ui.input name="title" :label="__('admin.cms.title')" :value="old('title', $announcement->title)" required />
            <div>
                <label for="body" class="mb-1.5 block text-sm font-medium">{{ __('admin.announcements.body') }}</label>
                <textarea id="body" name="body" rows="4" class="field">{{ old('body', $announcement->body) }}</textarea>
                @error('body')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <p class="mt-1.5 text-xs text-muted">{{ __('admin.cms.markdown_help') }}</p>
            </div>
            <x-ui.select name="level" :label="__('admin.announcements.level')" :value="$announcement->level" :options="collect(\App\Modules\Support\Models\Announcement::LEVELS)->mapWithKeys(fn ($l) => [$l => __('admin.announcements.level_'.$l)])->all()" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="starts_at" type="datetime-local" :label="__('admin.announcements.starts')" :value="old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i'))" />
                <x-ui.input name="ends_at" type="datetime-local" :label="__('admin.announcements.ends')" :value="old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i'))" />
            </div>
            <div class="flex gap-6"><x-ui.checkbox name="is_active" :label="__('admin.active')" :checked="$announcement->is_active" /><x-ui.checkbox name="is_dismissible" :label="__('admin.announcements.dismissible')" :checked="$announcement->is_dismissible" /></div>
        </x-ui.card>
        <div class="flex gap-3"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button><a href="{{ route('admin.announcements.index') }}" class="btn btn-ghost btn-lg">{{ __('admin.cancel') }}</a></div>
    </form>
</x-layouts.admin>
