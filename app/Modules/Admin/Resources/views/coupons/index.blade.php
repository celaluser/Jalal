<x-layouts.admin :title="__('admin.nav.coupons')">
    <x-ui.page-header :title="__('admin.nav.coupons')" :description="__('admin.coupons.description')">
        <x-slot:actions><a href="{{ route('admin.coupons.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('admin.coupons.new') }}</a></x-slot:actions>
    </x-ui.page-header>
    <x-ui.table>
        <thead><tr><th>{{ __('admin.coupons.code') }}</th><th>{{ __('admin.coupons.discount') }}</th><th>{{ __('admin.coupons.uses') }}</th><th>{{ __('admin.coupons.expires') }}</th><th>{{ __('admin.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($coupons as $coupon)
            <tr>
                <td class="font-mono font-medium">{{ $coupon->code }}</td>
                <td class="tnum">{{ $coupon->type === 'percent' ? rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.').'%' : number_format((float) $coupon->value, 2) }}</td>
                <td class="tnum">{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
                <td class="tnum text-muted">{{ $coupon->expires_at?->toDateString() ?? '—' }}</td>
                <td><x-ui.status :value="$coupon->is_active ? 'active' : 'inactive'" :label="$coupon->is_active ? __('admin.active') : __('admin.inactive')" /></td>
                <td class="whitespace-nowrap text-end">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.coupons.edit', $coupon) }}"><x-ui.icon name="pen" size="4" />{{ __('admin.edit') }}</a>
                    <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>
                </td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="6"><x-ui.empty icon="percent" :title="__('admin.coupons.empty_title')" :text="__('admin.coupons.empty_text')"><a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">{{ __('admin.coupons.new') }}</a></x-ui.empty></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $coupons->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
