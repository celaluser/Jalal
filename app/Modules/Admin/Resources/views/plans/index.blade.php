<x-layouts.admin :title="__('admin.nav.plans')">
    <x-ui.page-header :title="__('admin.nav.plans')" :description="__('admin.plans.description')">
        <x-slot:actions><a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('admin.plans.new') }}</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.table>
        <thead><tr><th>{{ __('admin.plans.name') }}</th><th>{{ __('admin.plans.price') }}</th><th>{{ __('admin.plans.trial') }}</th><th>{{ __('admin.plans.subscribers') }}</th><th>{{ __('admin.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($plans as $plan)
            <tr>
                <td><span class="font-medium">{{ $plan->name }}</span> @if ($plan->is_featured)<x-ui.badge tone="warning">{{ __('admin.plans.featured') }}</x-ui.badge>@endif<span class="block text-xs text-muted">{{ $plan->slug }}</span></td>
                <td class="tnum">{{ $plan->interval === 'free' ? __('billing.interval_free') : number_format((float) $plan->price, 2).' '.$plan->currency_code.' / '.__('billing.interval_'.$plan->interval) }}</td>
                <td class="tnum">{{ $plan->trial_days ?: '—' }}</td>
                <td class="tnum">{{ $plan->subscriptions_count }}</td>
                <td><x-ui.status :value="$plan->is_active ? 'active' : 'inactive'" :label="$plan->is_active ? __('admin.active') : __('admin.inactive')" /></td>
                <td class="whitespace-nowrap text-end">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.plans.edit', $plan) }}"><x-ui.icon name="pen" size="4" />{{ __('admin.edit') }}</a>
                    <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="6"><x-ui.empty icon="layers" :title="__('admin.plans.empty_title')" :text="__('admin.plans.empty_text')"><a href="{{ route('admin.plans.create') }}" class="btn btn-primary">{{ __('admin.plans.new') }}</a></x-ui.empty></td></tr>
        @endforelse
        </tbody>
    </x-ui.table>
</x-layouts.admin>
