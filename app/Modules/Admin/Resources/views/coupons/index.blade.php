<x-layouts.admin :title="__('admin.nav.coupons')">
    <div class="mb-4 flex justify-end"><a href="{{ route('admin.coupons.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('admin.coupons.new') }}</a></div>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
            <thead class="text-gray-500"><tr><th class="py-2 text-start">{{ __('admin.coupons.code') }}</th><th class="text-start">{{ __('admin.coupons.discount') }}</th><th class="text-start">{{ __('admin.coupons.uses') }}</th><th class="text-start">{{ __('admin.coupons.expires') }}</th><th class="text-start">{{ __('admin.status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($coupons as $coupon)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2 font-mono font-medium">{{ $coupon->code }}</td>
                    <td>{{ $coupon->type === 'percent' ? rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.').'%' : number_format((float) $coupon->value, 2) }}</td>
                    <td>{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
                    <td>{{ $coupon->expires_at?->toDateString() ?? '—' }}</td>
                    <td>{{ $coupon->is_active ? __('admin.active') : __('admin.inactive') }}</td>
                    <td class="text-end"><a class="text-brand-600 hover:underline" href="{{ route('admin.coupons.edit', $coupon) }}">{{ __('admin.edit') }}</a>
                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf @method('DELETE')<button class="ms-2 text-red-600 hover:underline">{{ __('admin.delete') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $coupons->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
