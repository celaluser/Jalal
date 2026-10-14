@if ($item['state']['status'] === 'buy')
    <bdi>{{ \App\Modules\Core\Models\Currency::where('code', $item['currency'])->first()?->format($item['price']) ?? number_format($item['price'], 2).' '.$item['currency'] }}</bdi>
    <span class="text-xs font-normal text-muted">{{ __('store.per_'.$item['billing']) }}</span>
@elseif (in_array($item['state']['status'], ['free', 'plan', 'owned'], true))
    <span class="text-muted">{{ __('store.nothing_to_pay') }}</span>
@else
    <span class="text-muted">{{ __('store.with_higher_plan') }}</span>
@endif
