<x-layouts.app :title="__('inventory.title')">
    <x-ui.page-header :title="__('inventory.title')" :description="__('inventory.subtitle')">
        <x-slot:actions><a href="{{ route('inventory.recipes') }}" class="btn btn-secondary">{{ __('inventory.recipes') }}</a></x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))<x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @if ($low > 0)<x-ui.alert type="warning" class="mb-4">{{ __('inventory.low_warning', ['count' => $low]) }}</x-ui.alert>@endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="space-y-3 lg:col-span-2" aria-labelledby="ing-h">
            <h2 id="ing-h" class="text-lg font-semibold">{{ __('inventory.ingredients') }}</h2>
            @if ($ingredients->isEmpty())
                <div class="card"><x-ui.empty icon="clipboard" :title="__('inventory.empty_title')" :text="__('inventory.empty_text')" /></div>
            @else
                <ul class="grid gap-2">
                    @foreach ($ingredients as $i)
                        <li class="card p-3">
                            <form method="POST" action="{{ route('inventory.update', $i->id) }}" class="grid items-end gap-2 sm:grid-cols-6">
                                @csrf @method('PUT')
                                <div class="sm:col-span-2"><label class="mb-1 block text-xs text-muted" for="n{{ $i->id }}">{{ __('inventory.name') }}</label><input id="n{{ $i->id }}" name="name" value="{{ $i->name }}" class="field" required maxlength="120"></div>
                                <div><label class="mb-1 block text-xs text-muted" for="u{{ $i->id }}">{{ __('inventory.unit') }}</label><select id="u{{ $i->id }}" name="unit" class="field">@foreach (\App\Modules\Inventory\Models\Ingredient::UNITS as $u)<option @selected($i->unit === $u)>{{ $u }}</option>@endforeach</select></div>
                                <div><label class="mb-1 block text-xs text-muted" for="s{{ $i->id }}">{{ __('inventory.stock') }}</label><input id="s{{ $i->id }}" name="stock_qty" type="number" step="0.001" value="{{ $i->stock_qty + 0 }}" class="field tnum"></div>
                                <div><label class="mb-1 block text-xs text-muted" for="l{{ $i->id }}">{{ __('inventory.low_at') }}</label><input id="l{{ $i->id }}" name="low_at" type="number" step="0.001" min="0" value="{{ $i->low_at }}" class="field tnum"></div>
                                <div><label class="mb-1 block text-xs text-muted" for="c{{ $i->id }}">{{ __('inventory.unit_cost') }}</label><input id="c{{ $i->id }}" name="unit_cost" type="number" step="0.0001" min="0" value="{{ $i->unit_cost }}" class="field tnum"></div>
                                <div class="flex flex-wrap items-center gap-2 sm:col-span-6">
                                    <select name="supplier_id" class="field !w-auto" aria-label="{{ __('inventory.supplier') }}"><option value="">{{ __('inventory.no_supplier') }}</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($i->supplier_id === $s->id)>{{ $s->name }}</option>@endforeach</select>
                                    @if ($i->isLow())<x-ui.badge tone="danger" dot>{{ __('inventory.low') }}</x-ui.badge>@endif
                                    @if ($i->stock_qty < 0)<span class="text-xs text-muted">{{ __('inventory.negative') }}</span>@endif
                                    <button class="btn btn-secondary btn-sm ms-auto">{{ __('inventory.save') }}</button>
                                    <button class="btn btn-ghost btn-sm" form="del{{ $i->id }}" onclick="return confirm(@js(__('inventory.confirm_delete')))">{{ __('inventory.delete') }}</button>
                                </div>
                            </form>
                            <form id="del{{ $i->id }}" method="POST" action="{{ route('inventory.destroy', $i->id) }}" class="hidden">@csrf @method('DELETE')</form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('inventory.store') }}" class="card card-pad grid items-end gap-3 sm:grid-cols-6">
                @csrf
                <h3 class="font-semibold sm:col-span-6">{{ __('inventory.add_ingredient') }}</h3>
                <div class="sm:col-span-2"><x-ui.input name="name" :label="__('inventory.name')" required maxlength="120" /></div>
                <div><label for="unit" class="mb-1.5 block text-sm font-medium">{{ __('inventory.unit') }}</label><select id="unit" name="unit" class="field">@foreach (\App\Modules\Inventory\Models\Ingredient::UNITS as $u)<option>{{ $u }}</option>@endforeach</select></div>
                <x-ui.input name="stock_qty" type="number" step="0.001" :label="__('inventory.stock')" />
                <x-ui.input name="low_at" type="number" step="0.001" min="0" :label="__('inventory.low_at')" />
                <x-ui.input name="unit_cost" type="number" step="0.0001" min="0" :label="__('inventory.unit_cost')" />
                <div class="sm:col-span-6"><x-ui.button :block="false">{{ __('inventory.save') }}</x-ui.button></div>
            </form>
        </section>

        <aside class="space-y-6">
            <form method="POST" action="{{ route('inventory.purchase') }}" class="card card-pad space-y-3">
                @csrf
                <h2 class="text-lg font-semibold">{{ __('inventory.restock') }}</h2>
                <p class="text-xs text-muted">{{ __('inventory.restock_help') }}</p>
                <div><label for="p-ing" class="mb-1.5 block text-sm font-medium">{{ __('inventory.ingredient') }}</label><select id="p-ing" name="ingredient_id" class="field" required>@foreach ($ingredients as $i)<option value="{{ $i->id }}">{{ $i->name }} ({{ $i->unit }})</option>@endforeach</select></div>
                <x-ui.input name="qty" type="number" step="0.001" min="0.001" :label="__('inventory.quantity')" required />
                <x-ui.input name="unit_cost" type="number" step="0.0001" min="0" :label="__('inventory.paid_per_unit')" required />
                <div><label for="p-sup" class="mb-1.5 block text-sm font-medium">{{ __('inventory.supplier') }}</label><select id="p-sup" name="supplier_id" class="field"><option value="">{{ __('inventory.no_supplier') }}</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                <x-ui.input name="purchased_on" type="date" :label="__('inventory.date')" :value="now()->toDateString()" />
                <x-ui.input name="note" :label="__('inventory.note')" maxlength="255" />
                <x-ui.button :block="false" :disabled="$ingredients->isEmpty()">{{ __('inventory.save') }}</x-ui.button>
            </form>

            @if ($purchases->isNotEmpty())
                <div class="card card-pad">
                    <h2 class="mb-2 text-lg font-semibold">{{ __('inventory.recent_purchases') }}</h2>
                    <ul class="space-y-1 text-sm">@foreach ($purchases as $p)<li class="flex justify-between gap-2"><span class="truncate">{{ $names[$p->ingredient_id] ?? '—' }} · {{ $p->qty + 0 }}</span><span class="tnum text-muted">{{ $restaurant->money($p->qty * $p->unit_cost) }}</span></li>@endforeach</ul>
                </div>
            @endif

            <div class="card card-pad space-y-3">
                <h2 class="text-lg font-semibold">{{ __('inventory.suppliers') }}</h2>
                <ul class="space-y-1 text-sm">@foreach ($suppliers as $s)
                    <li class="flex items-center justify-between gap-2"><span class="truncate">{{ $s->name }}@if ($s->phone) · <bdi>{{ $s->phone }}</bdi>@endif</span>
                        <form method="POST" action="{{ route('inventory.suppliers.destroy', $s->id) }}" onsubmit="return confirm(@js(__('inventory.confirm_delete')))">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">{{ __('inventory.delete') }}</button></form></li>
                @endforeach</ul>
                <form method="POST" action="{{ route('inventory.suppliers.store') }}" class="space-y-2">
                    @csrf
                    <input name="name" class="field" placeholder="{{ __('inventory.add_supplier') }}" required maxlength="120" aria-label="{{ __('inventory.add_supplier') }}">
                    <input name="phone" class="field" placeholder="{{ __('inventory.phone') }}" maxlength="40" aria-label="{{ __('inventory.phone') }}">
                    <button class="btn btn-secondary btn-sm">{{ __('inventory.save') }}</button>
                </form>
            </div>
        </aside>
    </div>
</x-layouts.app>
