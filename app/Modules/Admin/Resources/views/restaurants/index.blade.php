<x-layouts.admin :title="__('admin.nav.restaurants')">
    <x-ui.card class="mb-4">
        <form method="GET" class="grid gap-3 sm:grid-cols-5">
            <div class="sm:col-span-2"><x-ui.input name="q" :value="$filters['q']" :label="__('admin.search')" placeholder="{{ __('admin.restaurants.search_placeholder') }}" /></div>
            <x-ui.select name="status" :label="__('admin.status')" :value="$filters['status']" :placeholder="__('admin.all')" :options="['active' => __('admin.restaurants.status_active'), 'suspended' => __('admin.restaurants.status_suspended')]" />
            <x-ui.select name="plan" :label="__('admin.nav.plans')" :value="$filters['plan']" :placeholder="__('admin.all')" :options="$plans->pluck('name', 'id')->all()" />
            <div class="flex items-end gap-2">
                <label class="mb-2 flex items-center gap-1 text-sm"><input type="checkbox" name="trashed" value="1" @checked($filters['trashed'])> {{ __('admin.restaurants.deleted_only') }}</label>
                <x-ui.button class="!w-auto">{{ __('admin.filter') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
    <x-ui.card class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="text-start text-gray-500"><tr><th class="py-2 text-start">{{ __('admin.restaurants.name') }}</th><th class="text-start">{{ __('admin.restaurants.owner') }}</th><th class="text-start">{{ __('admin.restaurants.plan') }}</th><th class="text-start">{{ __('admin.status') }}</th><th class="text-start">{{ __('admin.restaurants.registered') }}</th></tr></thead>
            <tbody>
            @forelse ($restaurants as $restaurant)
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="py-2"><a class="font-medium text-brand-600 hover:underline" href="{{ route('admin.restaurants.show', $restaurant->id) }}">{{ $restaurant->name }}</a><div class="text-xs text-gray-500">/r/{{ $restaurant->slug }}</div></td>
                    <td>{{ $restaurant->owner?->email ?? '—' }}</td>
                    <td>{{ $current[$restaurant->id]->plan->name ?? '—' }}@if (($current[$restaurant->id] ?? null)?->status === 'trialing') <span class="text-xs text-amber-600">({{ __('admin.restaurants.trial') }})</span>@endif</td>
                    <td>@if ($restaurant->trashed()) {{ __('admin.restaurants.deleted') }} @else {{ __('admin.restaurants.status_'.$restaurant->status) }} @endif</td>
                    <td>{{ $restaurant->created_at->toDateString() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">{{ __('admin.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $restaurants->links() }}</div>
    </x-ui.card>
</x-layouts.admin>
