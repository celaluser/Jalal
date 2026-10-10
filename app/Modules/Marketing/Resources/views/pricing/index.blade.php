<x-layouts.app :title="__('marketing.pricing_title')">
    <x-ui.page-header :title="__('marketing.pricing_title')" :description="__('marketing.pricing_sub')" />

    <form method="POST" action="{{ route('pricing.store') }}" class="card card-pad mb-6 max-w-3xl space-y-5">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2"><x-ui.input name="name" :label="__('marketing.rule_name')" :placeholder="__('marketing.rule_name_ph')" required maxlength="80" /></div>
            <x-ui.input name="percent" type="number" min="1" max="90" :label="__('marketing.rule_percent')" required />
            <x-ui.input name="from_time" type="time" :label="__('marketing.rule_from')" value="16:00" required />
            <x-ui.input name="to_time" type="time" :label="__('marketing.rule_to')" value="18:00" required />
            <div>
                <label for="category_id" class="mb-1.5 block text-sm font-medium">{{ __('marketing.rule_scope') }}</label>
                <select id="category_id" name="category_id" class="field"><option value="">{{ __('marketing.rule_all_menu') }}</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name[$restaurant->locale] ?? collect($c->name)->first() }}</option>@endforeach</select>
            </div>
        </div>
        <fieldset><legend class="mb-1.5 text-sm font-medium">{{ __('marketing.rule_days') }}</legend>
            <div class="flex flex-wrap gap-2">@foreach ([1, 2, 3, 4, 5, 6, 0] as $d)<label class="chip"><input type="checkbox" name="days[]" value="{{ $d }}" class="me-1.5 accent-[var(--color-accent-600)]">{{ __('marketing.day_'.$d) }}</label>@endforeach</div>
            <p class="mt-1.5 text-xs text-muted">{{ __('marketing.rule_days_help') }}</p>
        </fieldset>
        <x-ui.button :block="false">{{ __('marketing.rule_add') }}</x-ui.button>
    </form>

    @if ($rules->isEmpty())
        <div class="card"><x-ui.empty icon="tag" :title="__('marketing.rules_empty')" :text="__('marketing.rules_empty_text')" /></div>
    @else
        <ul class="grid gap-3">
            @foreach ($rules as $r)
                <li class="card flex flex-wrap items-center gap-4 p-4">
                    <div class="tnum display text-2xl font-bold">−{{ $r->percent }}%</div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $r->name }}</p>
                        <p class="text-sm text-muted">{{ $r->from_time }}–{{ $r->to_time }} · {{ $r->days ? collect($r->days)->map(fn ($d) => __('marketing.day_'.$d))->implode(', ') : __('marketing.every_day') }} · {{ $r->category_id ? ($categories[$r->category_id]->name[$restaurant->locale] ?? '') : __('marketing.rule_all_menu') }}</p>
                    </div>
                    <x-ui.badge :tone="$r->is_active ? 'success' : 'neutral'" dot>{{ $r->is_active ? __('marketing.status_active') : __('marketing.status_off') }}</x-ui.badge>
                    <form method="POST" action="{{ route('pricing.toggle', $r->id) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $r->is_active ? __('marketing.turn_off') : __('marketing.turn_on') }}</button></form>
                    <form method="POST" action="{{ route('pricing.destroy', $r->id) }}" onsubmit="return confirm('{{ __('marketing.delete_rule_confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
