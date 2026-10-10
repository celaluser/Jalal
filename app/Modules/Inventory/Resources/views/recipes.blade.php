<x-layouts.app :title="__('inventory.recipes')">
    <x-ui.page-header :title="__('inventory.recipes')" :description="__('inventory.recipes_help')" :back="['url' => route('inventory.index'), 'label' => __('inventory.title')]" />
    @foreach ($categories as $c)
        @continue($c->products->isEmpty())
        <h2 class="mb-2 mt-6 text-lg font-semibold">{{ $c->tr('name') }}</h2>
        <ul class="grid gap-2">
            @foreach ($c->products as $p)
                <li class="card flex flex-wrap items-center gap-3 p-3">
                    <span class="min-w-0 flex-1 truncate font-medium">{{ $p->tr('name') }}</span>
                    @if (isset($lines[$p->id]))
                        <span class="text-sm text-muted tnum">{{ $costs[$p->id] !== null ? __('inventory.cost').': '.$restaurant->money($costs[$p->id]) : __('inventory.price_missing') }}</span>
                    @else
                        <span class="text-sm text-muted">{{ __('inventory.no_recipe') }}</span>
                    @endif
                    <a href="{{ route('inventory.recipe', $p->id) }}" class="btn btn-secondary btn-sm">{{ __('inventory.edit_recipe') }}</a>
                </li>
            @endforeach
        </ul>
    @endforeach
</x-layouts.app>
