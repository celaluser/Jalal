<x-layouts.app :title="__('inventory.recipe_for', ['name' => $product->tr('name')])">
    <x-ui.page-header :title="__('inventory.recipe_for', ['name' => $product->tr('name')])" :back="['url' => route('inventory.recipes'), 'label' => __('inventory.back')]" />
    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @if ($ingredients->isEmpty())
        <div class="card"><x-ui.empty icon="clipboard" :title="__('inventory.no_ingredients')" :text="__('inventory.empty_text')" /></div>
    @else
        <form method="POST" action="{{ route('inventory.recipe.update', $product->id) }}" class="card card-pad max-w-xl space-y-3">
            @csrf @method('PUT')
            <p class="text-sm text-muted">{{ __('inventory.recipe_note') }}</p>
            @foreach ($ingredients as $i)
                <div class="flex items-center gap-3">
                    <label for="q{{ $i->id }}" class="min-w-0 flex-1 truncate text-sm font-medium">{{ $i->name }}</label>
                    <input id="q{{ $i->id }}" name="qty[{{ $i->id }}]" type="number" step="0.001" min="0" value="{{ isset($qty[$i->id]) ? $qty[$i->id] + 0 : '' }}" class="field tnum !w-28" aria-label="{{ __('inventory.per_portion') }}: {{ $i->name }}">
                    <span class="w-10 text-sm text-muted">{{ $i->unit }}</span>
                </div>
            @endforeach
            <p class="text-sm tnum">{{ __('inventory.cost') }}: <strong>{{ $cost !== null ? $restaurant->money($cost) : '—' }}</strong></p>
            <x-ui.button :block="false">{{ __('inventory.save') }}</x-ui.button>
        </form>
    @endif
</x-layouts.app>
