<x-layouts.admin :title="$item['name']">
    <x-ui.page-header :title="$item['name']" :description="__('store.admin.edit_sub')" :back="['url' => route('admin.store.index'), 'label' => __('store.admin.title')]" />
    <form method="POST" action="{{ route('admin.store.update', $item['slug']) }}" class="max-w-3xl space-y-5" x-data="{ mode: @js(old('mode', $item['mode'])) }">
        @csrf @method('PUT')
        <x-ui.card :title="__('store.admin.availability')">
            <div class="grid gap-2" role="radiogroup">
                @foreach (\App\Modules\Store\Services\Catalog::MODES as $m)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line-strong p-3 has-[:checked]:border-accent-600">
                        <input type="radio" name="mode" value="{{ $m }}" x-model="mode" class="mt-1" required>
                        <span><span class="block font-medium">{{ __('store.admin.mode_'.$m) }}</span><span class="block text-sm text-muted">{{ __('store.admin.mode_help_'.$m.'_'.$item['kind']) }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('mode')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
        </x-ui.card>

        <x-ui.card :title="__('store.admin.pricing')" x-show="mode === 'paid'" x-cloak>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="billing" class="mb-1.5 block text-sm font-medium">{{ __('store.admin.billing') }}</label>
                    <select id="billing" name="billing" class="field">@foreach (\App\Modules\Store\Services\Catalog::BILLINGS as $b)<option value="{{ $b }}" @selected(old('billing', $item['billing']) === $b)>{{ __('store.admin.billing_'.$b) }}</option>@endforeach</select></div>
                <x-ui.input name="price" type="number" step="0.01" min="0" :label="__('store.admin.price')" :value="$item['price']" />
                <div><label for="currency_code" class="mb-1.5 block text-sm font-medium">{{ __('store.admin.currency') }}</label>
                    <select id="currency_code" name="currency_code" class="field">@foreach ($currencies as $c)<option value="{{ $c }}" @selected(old('currency_code', $item['currency']) === $c)>{{ $c }}</option>@endforeach</select></div>
                <x-ui.input name="trial_days" type="number" min="0" max="365" :label="__('store.admin.trial_days')" :value="$item['trial_days']" :hint="__('store.admin.trial_hint')" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('store.admin.listing')">
            <div class="space-y-4">
                <x-ui.input name="name" :label="__('store.admin.name')" :value="$item['name']" maxlength="80" />
                <x-ui.input name="summary" :label="__('store.admin.summary')" :value="$item['summary']" maxlength="200" />
                <div><label for="description" class="mb-1.5 block text-sm font-medium">{{ __('store.admin.description') }}</label><textarea id="description" name="description" rows="5" maxlength="4000" class="field">{{ old('description', $item['description']) }}</textarea></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="sort" type="number" :label="__('store.admin.sort')" :value="$item['sort']" />
                    <label class="flex items-center gap-2.5 self-end pb-2 text-sm"><input type="checkbox" name="visible" value="1" class="check" @checked(old('visible', $item['visible']))>{{ __('store.admin.visible') }}</label>
                </div>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button><a href="{{ route('admin.store.index') }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a></div>
    </form>
    @if ($item['customised'])
        <form method="POST" action="{{ route('admin.store.reset', $item['slug']) }}" class="mt-4" onsubmit="return confirm(@js(__('store.admin.confirm_reset')))">@csrf @method('DELETE')<button class="btn btn-ghost">{{ __('store.admin.reset') }}</button></form>
    @endif
</x-layouts.admin>
