<x-layouts.app :title="$category->exists ? __('menu.edit_category') : __('menu.new_category')">
    <x-ui.page-header :title="$category->exists ? __('menu.edit_category') : __('menu.new_category')" :back="['url' => route('menu.index'), 'label' => __('menu.title')]" />
    <div class="max-w-2xl space-y-5">
        @error('limit')<x-ui.alert type="warning"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ $message }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('menu.upgrade') }}</a></span></x-ui.alert>@enderror
        <form method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('menu.categories.update', $category) : route('menu.categories.store') }}" class="card card-pad space-y-5">
            @csrf @if ($category->exists) @method('PUT') @endif
            <x-ui.translatable name="name" :label="__('menu.name')" :locales="$locales" :values="$category->name ?? []" required :maxlength="120" />
            <x-ui.translatable name="description" :label="__('menu.description')" :locales="$locales" :values="$category->description ?? []" textarea :rows="2" :maxlength="500" />
            @include('menu::partials.image', ['model' => $category])
            <x-ui.checkbox name="is_active" :label="__('menu.is_active')" :checked="$category->is_active" />
            <div class="flex items-center justify-between gap-3 pt-2">
                <span>@if ($category->exists)<button type="submit" form="delete-category" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('admin.delete') }}</button>@endif</span>
                <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
            </div>
        </form>
        @if ($category->exists)<form id="delete-category" method="POST" action="{{ route('menu.categories.destroy', $category) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')</form>@endif
    </div>
</x-layouts.app>
