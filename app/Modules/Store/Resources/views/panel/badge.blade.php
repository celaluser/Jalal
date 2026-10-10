@switch($item['state']['status'])
    @case('free')<x-ui.badge tone="success" dot>{{ __('store.state_free') }}</x-ui.badge>@break
    @case('plan')<x-ui.badge tone="success" dot>{{ __('store.state_plan') }}</x-ui.badge>@break
    @case('owned')<x-ui.badge tone="success" dot>{{ $item['state']['until'] ? __('store.state_until', ['date' => $item['state']['until']->isoFormat('LL')]) : __('store.state_owned') }}</x-ui.badge>@break
    @case('buy')<x-ui.badge tone="warning">{{ __('store.state_buy') }}</x-ui.badge>@break
    @default<x-ui.badge>{{ __('store.state_upgrade') }}</x-ui.badge>
@endswitch
