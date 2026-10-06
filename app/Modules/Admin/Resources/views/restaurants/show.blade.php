<x-layouts.admin :title="$restaurant->name">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card>
                <h2 class="mb-3 font-semibold">{{ __('admin.restaurants.details') }}</h2>
                <form method="POST" action="{{ route('admin.restaurants.update', $restaurant->id) }}" class="grid gap-3 sm:grid-cols-3">
                    @csrf @method('PUT')
                    <x-ui.input name="name" :label="__('admin.restaurants.name')" :value="old('name', $restaurant->name)" required />
                    <x-ui.input name="slug" :label="__('admin.restaurants.slug')" :value="old('slug', $restaurant->slug)" required />
                    <x-ui.input name="locale" :label="__('admin.restaurants.locale')" :value="old('locale', $restaurant->locale)" required />
                    <div class="sm:col-span-3"><x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button></div>
                </form>
            </x-ui.card>
            <x-ui.card>
                <h2 class="mb-3 font-semibold">{{ __('admin.restaurants.subscription') }}</h2>
                @if ($subscription)
                    <p class="mb-3 text-sm">{{ $subscription->plan->name }} · {{ __('admin.subscriptions.status_'.$subscription->status) }}
                        @if ($subscription->ends_at) · {{ __('admin.subscriptions.ends') }} {{ $subscription->ends_at->toDateString() }}@endif</p>
                @else
                    <p class="mb-3 text-sm text-gray-500">{{ __('admin.restaurants.no_subscription') }}</p>
                @endif
                <form method="POST" action="{{ route('admin.subscriptions.store') }}" class="grid gap-3 sm:grid-cols-4">
                    @csrf
                    <input type="hidden" name="restaurant_id" value="{{ $restaurant->id }}">
                    <x-ui.select name="plan_id" :label="__('admin.subscriptions.assign_plan')" :options="$plans->pluck('name', 'id')->all()" required />
                    <x-ui.input name="ends_at" type="date" :label="__('admin.subscriptions.custom_end')" />
                    <div class="flex items-end pb-2"><x-ui.checkbox name="create_invoice" :label="__('admin.subscriptions.record_invoice')" /></div>
                    <div class="flex items-end"><x-ui.button>{{ __('admin.subscriptions.assign') }}</x-ui.button></div>
                </form>
                @if ($history->count())
                    <table class="mt-4 w-full text-sm">
                        <tbody>
                        @foreach ($history as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-800"><td class="py-1.5">{{ $row->plan->name }}</td><td>{{ __('admin.subscriptions.status_'.$row->status) }}</td><td>{{ $row->starts_at?->toDateString() }} → {{ $row->ends_at?->toDateString() ?? '∞' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </x-ui.card>
            <x-ui.card>
                <h2 class="mb-3 font-semibold">{{ __('admin.nav.invoices') }}</h2>
                <table class="w-full text-sm"><tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-t border-gray-100 dark:border-gray-800"><td class="py-1.5">{{ $invoice->number }}</td><td>{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td><td>{{ __('billing.status_'.$invoice->status) }}</td><td><a class="text-brand-600 hover:underline" href="{{ route('admin.invoices.pdf', $invoice->id) }}">PDF</a></td></tr>
                @empty
                    <tr><td class="py-2 text-gray-500">{{ __('admin.empty') }}</td></tr>
                @endforelse
                </tbody></table>
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <x-ui.card>
                <h2 class="mb-3 font-semibold">{{ __('admin.restaurants.actions') }}</h2>
                <div class="space-y-2">
                    @if ($restaurant->trashed())
                        <form method="POST" action="{{ route('admin.restaurants.restore', $restaurant->id) }}">@csrf<x-ui.button>{{ __('admin.restaurants.restore') }}</x-ui.button></form>
                    @else
                        @if ($restaurant->owner && ! $restaurant->isSuspended())
                            <form method="POST" action="{{ route('admin.restaurants.impersonate', $restaurant->id) }}">@csrf<x-ui.button>{{ __('admin.restaurants.login_as') }}</x-ui.button></form>
                        @endif
                        @if ($restaurant->isSuspended())
                            <form method="POST" action="{{ route('admin.restaurants.unsuspend', $restaurant->id) }}">@csrf<x-ui.button variant="secondary">{{ __('admin.restaurants.unsuspend') }}</x-ui.button></form>
                        @else
                            <form method="POST" action="{{ route('admin.restaurants.suspend', $restaurant->id) }}" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf<x-ui.button variant="secondary">{{ __('admin.restaurants.suspend') }}</x-ui.button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.restaurants.destroy', $restaurant->id) }}" onsubmit="return confirm('{{ __('admin.restaurants.confirm_delete') }}')">@csrf @method('DELETE')<x-ui.button variant="danger">{{ __('admin.delete') }}</x-ui.button></form>
                    @endif
                </div>
            </x-ui.card>
            <x-ui.card>
                <h2 class="mb-3 font-semibold">{{ __('admin.restaurants.staff') }}</h2>
                <ul class="space-y-1 text-sm">@foreach ($staff as $member)<li>{{ $member->name }} <span class="text-gray-500">· {{ $member->email }}</span></li>@endforeach</ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
