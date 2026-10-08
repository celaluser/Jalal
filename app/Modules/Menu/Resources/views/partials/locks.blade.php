{{-- Languages whose text was written by hand: AI translation leaves them alone. Needs $model and $locales. --}}
@if (count($locales) > 1)
    <fieldset>
        <legend class="mb-1 text-sm font-medium">{{ __('menu.lock_title') }}</legend>
        <div class="flex flex-wrap gap-4">
            @foreach (array_slice($locales, 1) as $code)
                <label class="flex cursor-pointer items-center gap-2 text-sm"><input type="checkbox" name="locked_locales[]" value="{{ $code }}" class="check" @checked(in_array($code, old('locked_locales', $model->locked_locales ?? [])))>{{ strtoupper($code) }}</label>
            @endforeach
        </div>
        <p class="mt-1 text-xs text-muted">{{ __('menu.lock_hint') }}</p>
    </fieldset>
@endif
