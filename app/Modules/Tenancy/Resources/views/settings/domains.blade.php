<x-layouts.app :title="__('domains.title')">
    <x-ui.page-header :title="__('domains.title')" :description="__('domains.subtitle')" />

    <div class="max-w-3xl space-y-6">
        @php($fallback = route("storefront.menu", $restaurant->slug))

        <x-ui.card :title="__('domains.default_address')" :description="__('domains.default_help')">
            <div class="flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 font-mono text-sm" x-data="{ done: false }">
                <span class="min-w-0 flex-1 truncate" dir="ltr">{{ $fallback }}</span>
                <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard.writeText('{{ $fallback }}'); done = true; setTimeout(() => done = false, 1500)"><span x-text="done ? '{{ __('domains.copied') }}' : '{{ __('domains.copy') }}'"></span></button>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('domains.subdomain')" :description="__('domains.subdomain_help')">
            @if ($domains->subdomainsAvailable())
                <form method="POST" action="{{ route('domains.subdomain') }}" class="space-y-3" x-data="{ v: @js(old('subdomain', $restaurant->subdomain)) }">
                    @csrf @method('PUT')
                    <div class="flex items-stretch gap-2" dir="ltr">
                        <input id="subdomain" name="subdomain" x-model="v" class="field min-w-0 flex-1" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="40" aria-label="{{ __('domains.subdomain') }}" @error('subdomain') aria-invalid="true" @enderror>
                        <span class="flex items-center rounded-xl bg-surface-2 px-3 text-sm text-muted">.{{ config('tenancy.base_domain') }}</span>
                    </div>
                    @error('subdomain')<p class="flex items-center gap-1 text-sm text-red-600 dark:text-red-400" role="alert"><x-ui.icon name="alert" size="4" />{{ $message }}</p>@enderror
                    <p class="text-xs text-muted" x-show="v">{{ __('domains.subdomain_preview') }} <span class="font-mono" dir="ltr" x-text="'https://' + v + '.{{ config('tenancy.base_domain') }}'"></span></p>
                    <x-ui.button :block="false">{{ __('domains.save') }}</x-ui.button>
                </form>
            @else
                <p class="text-sm text-muted">{{ __('domains.subdomain_off') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('domains.custom_domain')" :description="__('domains.custom_help')">
            @if ($domains->customDomainsAvailable($restaurant))
                <form method="POST" action="{{ route('domains.custom') }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div dir="ltr"><x-ui.input name="custom_domain" :value="$restaurant->custom_domain" placeholder="menu.yourrestaurant.com" autocomplete="off" autocapitalize="off" spellcheck="false" /></div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button :block="false">{{ __('domains.save') }}</x-ui.button>
                        @if ($restaurant->custom_domain)
                            @if ($restaurant->domain_verified_at)<x-ui.badge tone="success">{{ __('domains.status_verified') }}</x-ui.badge>
                            @else<x-ui.badge tone="warning">{{ __('domains.status_pending') }}</x-ui.badge>@endif
                        @endif
                    </div>
                    <p class="text-xs text-muted">{{ __('domains.remove_domain') }}</p>
                </form>

                @if ($restaurant->custom_domain && ! $restaurant->domain_verified_at)
                    <div class="mt-6 space-y-4 border-t border-line pt-5">
                        <ol class="list-decimal space-y-1 ps-5 text-sm text-muted">
                            <li>{{ __('domains.step1') }}</li><li>{{ __('domains.step2') }}</li><li>{{ __('domains.step3') }}</li>
                        </ol>
                        <dl class="grid gap-3 rounded-xl bg-surface-2 p-4 text-sm sm:grid-cols-[auto_1fr]" dir="ltr">
                            <dt class="text-muted">{{ __('domains.record_type') }}</dt><dd class="font-mono">TXT</dd>
                            <dt class="text-muted">{{ __('domains.record_host') }}</dt><dd class="break-all font-mono">{{ $domains->txtHost($restaurant) }}</dd>
                            <dt class="text-muted">{{ __('domains.record_value') }}</dt><dd class="break-all font-mono">{{ $domains->verificationToken($restaurant) }}</dd>
                        </dl>
                        @if (config('tenancy.base_domain'))<p class="text-sm text-muted">{{ __('domains.cname_hint', ['target' => config('tenancy.base_domain')]) }}</p>@endif
                        @if ($dnsInstructions !== '')
                            <div class="rounded-xl border border-line p-4 text-sm"><p class="mb-1 font-medium">{{ __('domains.platform_instructions') }}</p><p class="whitespace-pre-line text-muted">{{ $dnsInstructions }}</p></div>
                        @endif
                        <form method="POST" action="{{ route('domains.verify') }}">@csrf<x-ui.button :block="false" icon="refresh">{{ __('domains.check_dns') }}</x-ui.button></form>
                    </div>
                @endif
            @elseif ($domains->customDomainNeedsUpgrade($restaurant))
                <x-ui.alert type="warning"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ __('domains.custom_upgrade') }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('domains.upgrade') }}</a></span></x-ui.alert>
            @else
                <p class="text-sm text-muted">{{ __('domains.custom_off') }}</p>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
