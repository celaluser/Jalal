<x-layouts.admin :title="__('admin.nav.restaurants')">
    <x-ui.page-header :title="__('admin.nav.restaurants')" :description="__('admin.restaurants.description')" />

    <form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-[14rem] flex-1"><x-ui.input name="q" :value="$filters['q']" :label="__('admin.search')" :placeholder="__('admin.restaurants.search_placeholder')" /></div>
        <div class="w-40"><x-ui.select name="status" :label="__('admin.status')" :value="$filters['status']" :placeholder="__('admin.all')" :options="['active' => __('admin.restaurants.status_active'), 'suspended' => __('admin.restaurants.status_suspended')]" /></div>
        <div class="w-44"><x-ui.select name="plan" :label="__('admin.restaurants.plan')" :value="$filters['plan']" :placeholder="__('admin.all')" :options="$plans->pluck('name', 'id')->all()" /></div>
        <label class="flex items-center gap-2 pb-2.5 text-sm"><input type="checkbox" name="trashed" value="1" class="check" @checked($filters['trashed'])> {{ __('admin.restaurants.deleted_only') }}</label>
        <x-ui.button :block="false" icon="search">{{ __('admin.filter') }}</x-ui.button>
    </form>

    <x-ui.table>
        <thead><tr><th>{{ __('admin.restaurants.name') }}</th><th>{{ __('admin.restaurants.owner') }}</th><th>{{ __('admin.restaurants.plan') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.restaurants.registered') }}</th></tr></thead>
        <tbody>
        @forelse ($restaurants as $restaurant)
            @php($sub = $current[$restaurant->id] ?? null)
            <tr>
                <td>
                    <a class="flex items-center gap-3" href="{{ route('admin.restaurants.show', $restaurant->id) }}">
                        <x-ui.avatar :name="$restaurant->name" size="9" />
                        <span><span class="link block">{{ $restaurant->name }}</span><span class="block text-xs text-muted">/r/{{ $restaurant->slug }}</span></span>
                    </a>
                </td>
                <td class="text-muted">{{ $restaurant->owner?->email ?? '—' }}</td>
                <td>@if ($sub){{ $sub->plan->name }} @if ($sub->status === 'trialing')<x-ui.badge tone="info">{{ __('admin.restaurants.trial') }}</x-ui.badge>@endif @else<span class="text-muted">—</span>@endif</td>
                <td>@if ($restaurant->trashed())<x-ui.status value="deleted" :label="__('admin.restaurants.deleted')" />@else<x-ui.status :value="$restaurant->status" :label="__('admin.restaurants.status_'.$restaurant->status)" />@endif</td>
                <td class="tnum text-muted">{{ $restaurant->created_at->toDateString() }}</td>
            </tr>
        @empty
            <tr class="hover:!bg-transparent"><td colspan="5"><x-ui.empty icon="store" :title="__('admin.empty')" /></td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $restaurants->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
