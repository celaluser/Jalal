{{-- Photo field with preview. Expects $model with image_media_id and an `image` relation. --}}
<div x-data="{ preview: @js($model->image?->url()) }">
    <label for="image" class="mb-1.5 block text-sm font-medium">{{ __('menu.image') }}</label>
    <div class="flex items-start gap-4">
        <div class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-2xl bg-surface-2 ring-1 ring-line">
            <img x-show="preview" :src="preview" alt="" class="size-full object-cover">
            <span x-show="!preview" class="text-muted"><x-ui.icon name="image" size="6" /></span>
        </div>
        <div class="min-w-0 flex-1">
            <input id="image" name="image" type="file" accept="image/png,image/jpeg,image/webp" class="field file:me-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                   x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview">
            <p class="mt-1.5 text-xs text-muted">{{ __('menu.image_hint') }}</p>
            @error('image')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            @if ($model->image_media_id)<label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1" class="check" x-on:change="if ($event.target.checked) preview = null">{{ __('menu.remove_image') }}</label>@endif
        </div>
    </div>
</div>
