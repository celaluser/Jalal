<x-layouts.app :title="$banner->exists ? __('marketing.edit_banner') : __('marketing.new_banner')">
    <x-ui.page-header :title="$banner->exists ? __('marketing.edit_banner') : __('marketing.new_banner')" :back="['url' => route('banners.index'), 'label' => __('marketing.banners_title')]" />
    <div class="max-w-2xl space-y-5">
        <form method="POST" enctype="multipart/form-data" action="{{ $banner->exists ? route('banners.update', $banner->id) : route('banners.store') }}" class="card card-pad space-y-5">
            @csrf @if ($banner->exists) @method('PUT') @endif
            <x-ui.translatable name="title" :label="__('marketing.banner_title')" :locales="$locales" :values="$banner->title ?? []" required :maxlength="100" />
            <x-ui.translatable name="text" :label="__('marketing.banner_text')" :locales="$locales" :values="$banner->text ?? []" textarea :rows="2" :maxlength="300" />
            @include('menu::partials.image', ['model' => $banner])
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="link_url" :label="__('marketing.banner_link')" :value="$banner->link_url" :hint="__('marketing.banner_link_hint')" maxlength="500" />
                <x-ui.translatable name="button" :label="__('marketing.banner_button')" :locales="$locales" :values="$banner->button ?? []" :maxlength="30" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="starts_on" type="date" :label="__('marketing.banner_starts')" :value="$banner->starts_on?->toDateString()" />
                <x-ui.input name="ends_on" type="date" :label="__('marketing.banner_ends')" :value="$banner->ends_on?->toDateString()" />
            </div>
            <x-ui.checkbox name="is_popup" :label="__('marketing.banner_popup')" :checked="$banner->is_popup" />
            <x-ui.checkbox name="is_active" :label="__('menu.is_active')" :checked="$banner->is_active" />
            <div class="flex items-center justify-between gap-3 pt-2">
                <span>@if ($banner->exists)<button type="submit" form="delete-banner" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('admin.delete') }}</button>@endif</span>
                <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
            </div>
        </form>
        @if ($banner->exists)<form id="delete-banner" method="POST" action="{{ route('banners.destroy', $banner->id) }}" onsubmit="return confirm('{{ __('menu.delete_confirm') }}')">@csrf @method('DELETE')</form>@endif
    </div>
</x-layouts.app>
