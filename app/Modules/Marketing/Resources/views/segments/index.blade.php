<x-layouts.app :title="__('marketing.segments_title')">
    <x-ui.page-header :title="__('marketing.segments_title')" :description="__('marketing.segments_sub')" />

    <form method="POST" action="{{ route('segments.store') }}" class="card card-pad mb-6 max-w-3xl space-y-5">
        @csrf
        <x-ui.input name="name" :label="__('marketing.segment_name')" :placeholder="__('marketing.segment_name_ph')" required maxlength="80" />
        <p class="text-sm text-muted">{{ __('marketing.segment_rules_help') }}</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.input name="min_orders" type="number" min="0" :label="__('marketing.rule_min_orders')" />
            <x-ui.input name="max_orders" type="number" min="0" :label="__('marketing.rule_max_orders')" />
            <x-ui.input name="min_spent" type="number" step="0.01" min="0" :label="__('marketing.rule_min_spent').' ('.$restaurant->currency_code.')'" />
            <x-ui.input name="active_within_days" type="number" min="0" :label="__('marketing.rule_active_within')" />
            <x-ui.input name="inactive_for_days" type="number" min="0" :label="__('marketing.rule_inactive_for')" />
            <x-ui.input name="joined_within_days" type="number" min="0" :label="__('marketing.rule_joined_within')" />
            <div><label for="birthday" class="mb-1.5 block text-sm font-medium">{{ __('marketing.rule_birthday') }}</label>
                <select id="birthday" name="birthday" class="field"><option value="">–</option><option value="this_month">{{ __('marketing.rule_birthday_this') }}</option><option value="next_month">{{ __('marketing.rule_birthday_next') }}</option></select></div>
            <div><label for="tier" class="mb-1.5 block text-sm font-medium">{{ __('marketing.rule_tier') }}</label>
                <select id="tier" name="tier" class="field"><option value="">–</option>@foreach (['bronze', 'silver', 'gold'] as $t)<option value="{{ $t }}">{{ ucfirst(__('marketing.rule_value_'.$t)) }}</option>@endforeach</select></div>
        </div>
        <x-ui.button :block="false">{{ __('marketing.segment_save') }}</x-ui.button>
    </form>

    @if ($segments->isEmpty())
        <div class="card"><x-ui.empty icon="users" :title="__('marketing.segments_empty')" :text="__('marketing.segments_empty_text')" /></div>
    @else
        <ul class="grid gap-3">@foreach ($segments as $s)
            <li class="card flex flex-wrap items-center gap-4 p-4">
                <div class="min-w-0 flex-1"><p class="font-semibold">{{ $s->name }}</p>
                    <p class="text-sm text-muted">{{ collect($s->rules)->map(fn ($v, $k) => __('marketing.rule_label_'.$k, ['value' => $k === 'min_spent' ? $restaurant->money($v / 100) : (in_array($k, ['birthday', 'tier']) ? __('marketing.rule_value_'.$v) : $v)]))->implode(' · ') ?: __('marketing.segment_everyone') }}</p></div>
                <x-ui.badge tone="info">{{ trans_choice('marketing.segment_reach', $s->reach, ['count' => $s->reach]) }}</x-ui.badge>
                <form method="POST" action="{{ route('segments.destroy', $s->id) }}" onsubmit="return confirm('{{ __('marketing.segment_delete_confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
            </li>
        @endforeach</ul>
    @endif
</x-layouts.app>
