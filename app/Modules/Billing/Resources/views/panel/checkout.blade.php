<x-layouts.app :title="__('billing.checkout')">
    <x-ui.page-header :title="__('billing.checkout')" :description="__('billing.checkout_sub')" />

    <form method="POST" action="{{ route('billing.start', $plan->slug) }}" class="grid gap-5 lg:grid-cols-5"
          x-data="checkout(@js($lines), @js(route('billing.preview', $plan->slug)), @js($coupon))">
        @csrf
        <div class="space-y-5 lg:col-span-3">
            <x-ui.card :title="__('billing.payment_method')">
                @if (count($gateways))
                    <div class="grid gap-2" role="radiogroup">
                        @foreach ($gateways as $code => $gateway)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong bg-surface p-3.5 transition hover:bg-surface-2 has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 has-[:checked]:ring-2 has-[:checked]:ring-accent-600/20 dark:has-[:checked]:bg-accent-900/20">
                                <input type="radio" name="gateway" value="{{ $code }}" class="size-4 accent-[var(--color-accent-600)]" @checked(old('gateway', array_key_first($gateways)) === $code) required>
                                <span class="font-medium">{{ $gateway->name() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('gateway')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <p class="mt-4 flex items-start gap-2 text-xs text-muted"><x-ui.icon name="shield" size="4" class="mt-0.5 shrink-0" />{{ __('billing.secure_note') }}</p>
                @else
                    <x-ui.empty icon="credit-card" :title="__('billing.no_gateways_title')" :text="__('billing.no_gateways_text')" class="!py-6" />
                @endif
            </x-ui.card>
        </div>

        <div class="lg:col-span-2">
            <x-ui.card :title="__('billing.order_summary')" class="lg:sticky lg:top-6">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="display text-lg font-semibold">{{ $plan->name }}</p><p class="text-sm text-muted">{{ __('billing.interval_'.$plan->interval) }}</p></div>
                </div>

                <div class="mt-5">
                    <label for="coupon" class="mb-1.5 block text-sm font-medium">{{ __('billing.coupon') }}</label>
                    <div class="flex gap-2">
                        <input id="coupon" name="coupon" x-model="code" class="field uppercase" maxlength="60" autocomplete="off" x-on:keydown.enter.prevent="apply()">
                        <button type="button" class="btn btn-secondary" x-on:click="apply()">{{ __('billing.apply') }}</button>
                    </div>
                    <p class="mt-1.5 text-sm" x-show="message" x-cloak :class="ok ? 'text-accent-700 dark:text-accent-300' : 'text-red-600 dark:text-red-400'" x-text="message" role="status"></p>
                </div>

                <dl class="mt-5 space-y-2 border-t border-line pt-4 text-sm tnum">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('billing.subtotal') }}</dt><dd><bdi x-text="lines.subtotal"></bdi></dd></div>
                    <div class="flex justify-between text-accent-700 dark:text-accent-300" x-show="lines.discount" x-cloak><dt>{{ __('billing.discount') }}</dt><dd><bdi x-text="lines.discount"></bdi></dd></div>
                    <div class="flex justify-between" x-show="lines.tax" x-cloak><dt class="text-muted">{{ __('billing.tax') }} <span x-text="lines.tax_rate"></span><span x-show="lines.inclusive"> ({{ __('billing.tax_included') }})</span></dt><dd><bdi x-text="lines.tax"></bdi></dd></div>
                    <div class="flex justify-between border-t border-line pt-3 text-base font-semibold"><dt>{{ __('billing.total_due') }}</dt><dd><bdi x-text="lines.total"></bdi></dd></div>
                </dl>

                @error('checkout')<x-ui.alert type="error" class="mt-4">{{ $message }}</x-ui.alert>@enderror
                <button class="btn btn-primary btn-lg mt-5 w-full" @disabled(! count($gateways))>{{ __('billing.checkout') }}</button>
            </x-ui.card>
        </div>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('checkout', (lines, url, code) => ({
                lines, code, ok: false, message: '',
                async apply() {
                    if (!this.code.trim()) { return; }
                    const res = await fetch(url + '?coupon=' + encodeURIComponent(this.code), { headers: { Accept: 'application/json' } });
                    const body = await res.json();
                    this.ok = res.ok && body.ok;
                    this.message = this.ok ? @js(__('billing.coupon_ok')) : body.message;
                    if (this.ok) { this.lines = body.lines; }
                },
                init() { if (this.code) { this.apply(); } },
            }));
        });
    </script>
</x-layouts.app>
