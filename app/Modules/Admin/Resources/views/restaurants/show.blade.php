<x-layouts.admin :title="$restaurant->name">
    <x-ui.page-header :title="$restaurant->name" :back="['url' => route('admin.restaurants.index'), 'label' => __('admin.nav.restaurants')]" :description="'/r/'.$restaurant->slug">
        <x-slot:actions>
            @if ($restaurant->badgeKey())<x-ui.badge tone="info">{{ __('platform.badge_'.$restaurant->badgeKey()) }}</x-ui.badge>@endif
            @if ($restaurant->is_featured)<x-ui.badge tone="success">{{ __('platform.featured') }}</x-ui.badge>@endif
            @if ($restaurant->is_limited)<x-ui.badge tone="warning">{{ __('platform.limited') }}</x-ui.badge>@endif
            @if ($restaurant->trashed())<x-ui.status value="deleted" :label="__('admin.restaurants.deleted')" />@else<x-ui.status :value="$restaurant->status" :label="__('admin.restaurants.status_'.$restaurant->status)" />@endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-ui.card :title="__('admin.restaurants.details')">
                <form method="POST" action="{{ route('admin.restaurants.update', $restaurant->id) }}" class="grid gap-4 sm:grid-cols-3">
                    @csrf @method('PUT')
                    <x-ui.input name="name" :label="__('admin.restaurants.name')" :value="old('name', $restaurant->name)" required />
                    <x-ui.input name="slug" :label="__('admin.restaurants.slug')" :value="old('slug', $restaurant->slug)" required />
                    <x-ui.input name="locale" :label="__('admin.restaurants.locale')" :value="old('locale', $restaurant->locale)" required />
                    <div class="sm:col-span-3"><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button></div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('platform.options_title')" :description="__('platform.options_sub')">
                <form method="POST" action="{{ route('admin.restaurants.options', $restaurant->id) }}" class="space-y-6">
                    @csrf @method('PUT')

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold">{{ __('platform.showcase') }}</legend>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.select name="badge" :label="__('platform.badge')" :value="$restaurant->badge" :options="collect(\App\Modules\Tenancy\Models\Restaurant::BADGES)->mapWithKeys(fn ($b) => [$b => __('platform.badge_'.$b)])->all()" :placeholder="__('platform.badge_none')" />
                            <x-ui.input name="featured_order" type="number" min="0" max="9999" :label="__('platform.featured_order')" :value="old('featured_order', $restaurant->featured_order)" />
                            <div class="space-y-2 pt-1 sm:pt-7">
                                <x-ui.checkbox name="is_premium" :label="__('platform.is_premium')" :checked="$restaurant->is_premium" />
                                <x-ui.checkbox name="is_featured" :label="__('platform.is_featured')" :checked="$restaurant->is_featured" />
                            </div>
                        </div>
                        <p class="text-xs text-muted">{{ __('platform.showcase_help') }}</p>
                    </fieldset>

                    <fieldset class="space-y-3" x-data="{ on: {{ old('is_limited', $restaurant->is_limited) ? 'true' : 'false' }} }">
                        <legend class="text-sm font-semibold">{{ __('platform.limits_title') }}</legend>
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm"><input type="checkbox" name="is_limited" value="1" class="check" x-model="on" @checked(old('is_limited', $restaurant->is_limited))> <span>{{ __('platform.is_limited') }}</span></label>
                        <p class="text-xs text-muted">{{ __('platform.limits_help', ['plan' => $subscription?->plan->name ?? __('platform.no_plan')]) }}</p>
                        <div class="grid gap-3 sm:grid-cols-3" x-show="on" x-cloak>
                            @foreach (\App\Modules\Billing\Models\Plan::LIMITS as $key)
                                @php($ov = $restaurant->limit_overrides[$key] ?? '')
                                <div>
                                    <label for="limit-{{ $key }}" class="mb-1.5 block text-sm font-medium">{{ __('admin.plans.limit_'.$key) }}</label>
                                    <input id="limit-{{ $key }}" name="limits[{{ $key }}]" class="field" value="{{ old('limits.'.$key, $ov) }}" inputmode="numeric" placeholder="{{ $subscription?->plan->limit($key) ?? '∞' }}" pattern="\d{1,9}|unlimited" title="{{ __('platform.limit_format') }}">
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-muted" x-show="on" x-cloak>{{ __('platform.limit_format') }}</p>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold">{{ __('platform.features_title') }}</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach (\App\Modules\Billing\Models\Plan::FEATURES as $key)
                                @php($cur = $restaurant->feature_overrides[$key] ?? null)
                                <div>
                                    <label for="feature-{{ $key }}" class="mb-1.5 block text-sm font-medium">{{ __('admin.plans.feature_'.$key) }}</label>
                                    <select id="feature-{{ $key }}" name="features[{{ $key }}]" class="field">
                                        <option value="">{{ __('platform.follow_plan') }}{{ $subscription ? ' ('.($subscription->plan->hasFeature($key) ? __('platform.on') : __('platform.off')).')' : '' }}</option>
                                        <option value="on" @selected($cur === true)>{{ __('platform.force_on') }}</option>
                                        <option value="off" @selected($cur === false)>{{ __('platform.force_off') }}</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold">{{ __('platform.theme_title') }}</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select name="theme" :label="__('platform.theme')" :value="$restaurant->theme" :options="$themes" />
                            <div class="pt-1 sm:pt-7"><x-ui.checkbox name="theme_locked" :label="__('platform.theme_locked')" :checked="$restaurant->theme_locked" /></div>
                        </div>
                    </fieldset>

                    <div>
                        <label for="admin_notes" class="mb-1.5 block text-sm font-medium">{{ __('platform.notes') }}</label>
                        <textarea id="admin_notes" name="admin_notes" rows="3" maxlength="2000" class="field">{{ old('admin_notes', $restaurant->admin_notes) }}</textarea>
                        <p class="mt-1 text-xs text-muted">{{ __('platform.notes_help') }}</p>
                    </div>

                    <div><x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button></div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('domains.title')">
                <form method="POST" action="{{ route('admin.restaurants.domain', $restaurant->id) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    @error('domain')<div class="sm:col-span-2"><x-ui.alert type="error">{{ $message }}</x-ui.alert></div>@enderror
                    <x-ui.input name="subdomain" :label="__('domains.subdomain')" :value="$restaurant->subdomain" />
                    <x-ui.input name="custom_domain" :label="__('domains.custom_domain')" :value="$restaurant->custom_domain" />
                    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="verified" value="1" @checked($restaurant->domain_verified_at) class="size-4 accent-[var(--color-accent-600)]"> {{ __('domains.verified_manual') }}</label>
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
                        @if ($restaurant->custom_domain)<button type="submit" form="verify-domain" class="btn btn-secondary">{{ __('domains.check_dns') }}</button>@endif
                    </div>
                </form>
                @if ($restaurant->custom_domain)<form id="verify-domain" method="POST" action="{{ route('admin.restaurants.domain.verify', $restaurant->id) }}">@csrf</form>@endif
            </x-ui.card>

            <x-ui.card :title="__('admin.restaurants.subscription')" :description="$subscription ? $subscription->plan->name.' · '.__('admin.subscriptions.status_'.$subscription->status).($subscription->ends_at ? ' · '.__('admin.subscriptions.ends').' '.$subscription->ends_at->toDateString() : '') : __('admin.restaurants.no_subscription')">
                <form method="POST" action="{{ route('admin.subscriptions.store') }}" class="grid items-end gap-4 sm:grid-cols-4">
                    @csrf
                    <input type="hidden" name="restaurant_id" value="{{ $restaurant->id }}">
                    <x-ui.select name="plan_id" :label="__('admin.subscriptions.assign_plan')" :options="$plans->pluck('name', 'id')->all()" required />
                    <x-ui.input name="ends_at" type="date" :label="__('admin.subscriptions.custom_end')" />
                    <div class="pb-2.5"><x-ui.checkbox name="create_invoice" :label="__('admin.subscriptions.record_invoice')" /></div>
                    <x-ui.button>{{ __('admin.subscriptions.assign') }}</x-ui.button>
                </form>
                @if ($history->count())
                    <ul class="mt-5 divide-y divide-line border-t border-line text-sm">
                        @foreach ($history as $row)
                            <li class="flex items-center justify-between gap-3 py-2.5"><span class="font-medium">{{ $row->plan->name }}</span><x-ui.status :value="$row->status" :label="__('admin.subscriptions.status_'.$row->status)" /><span class="tnum text-xs text-muted">{{ $row->starts_at?->toDateString() }} → {{ $row->ends_at?->toDateString() ?? '∞' }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('admin.nav.invoices')" :pad="false">
                <ul class="divide-y divide-line text-sm">
                    @forelse ($invoices as $invoice)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6"><span class="font-medium tnum">{{ $invoice->number }}</span><span class="tnum">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</span><x-ui.status :value="$invoice->status" :label="__('billing.status_'.$invoice->status)" /><a class="link inline-flex items-center gap-1" href="{{ route('admin.invoices.pdf', $invoice->id) }}"><x-ui.icon name="download" size="4" />PDF</a></li>
                    @empty
                        <li><x-ui.empty icon="receipt" :title="__('admin.empty')" /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>

        <div class="space-y-5">
            <x-ui.card :title="__('admin.restaurants.actions')">
                <div class="space-y-2">
                    @if ($restaurant->trashed())
                        <form method="POST" action="{{ route('admin.restaurants.restore', $restaurant->id) }}">@csrf<x-ui.button icon="refresh">{{ __('admin.restaurants.restore') }}</x-ui.button></form>
                    @else
                        @if ($restaurant->owner && ! $restaurant->isSuspended())
                            <form method="POST" action="{{ route('admin.restaurants.impersonate', $restaurant->id) }}">@csrf<x-ui.button variant="dark" icon="log-out">{{ __('admin.restaurants.login_as') }}</x-ui.button></form>
                        @endif
                        @if ($restaurant->isSuspended())
                            <form method="POST" action="{{ route('admin.restaurants.unsuspend', $restaurant->id) }}">@csrf<x-ui.button variant="secondary">{{ __('admin.restaurants.unsuspend') }}</x-ui.button></form>
                        @else
                            <form method="POST" action="{{ route('admin.restaurants.suspend', $restaurant->id) }}" onsubmit="return confirm('{{ __('admin.confirm') }}')">@csrf<x-ui.button variant="secondary">{{ __('admin.restaurants.suspend') }}</x-ui.button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.restaurants.destroy', $restaurant->id) }}" onsubmit="return confirm('{{ __('admin.restaurants.confirm_delete') }}')">@csrf @method('DELETE')<x-ui.button variant="danger" icon="trash">{{ __('admin.delete') }}</x-ui.button></form>
                    @endif
                </div>
            </x-ui.card>
            <x-ui.card :title="__('admin.restaurants.staff')" :pad="false">
                <ul class="divide-y divide-line">
                    @foreach ($staff as $member)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6"><x-ui.avatar :name="$member->name" size="8" /><span class="min-w-0"><span class="block truncate text-sm font-medium">{{ $member->name }}</span><span class="block truncate text-xs text-muted">{{ $member->email }}</span></span></li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
