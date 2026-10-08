@php
    $config['t'] = [
        'required' => __('orders.pos_required'), 'optional' => __('orders.pos_optional'), 'upTo' => __('orders.pos_up_to'),
        'sent' => __('orders.pos_sent'), 'failed' => __('orders.pos_failed'),
    ];
@endphp
@push('head')@vite(['resources/js/orders.js'])@endpush
<x-layouts.app :title="__('orders.pos_title')">
    <div x-data="posApp(@js($config))" x-on:keydown.escape.window="sheet = null; drawer = false">
        <x-ui.page-header :title="__('orders.pos_title')" :description="__('orders.pos_sub')" :back="['url' => route('orders.board'), 'label' => __('orders.board_title')]" />

        <div class="grid items-start gap-5 lg:grid-cols-[1fr_24rem]">
            {{-- Menu --}}
            <section class="min-w-0" aria-label="{{ __('orders.pos_title') }}">
                <div class="sticky top-16 z-10 -mx-1 space-y-3 bg-bg/90 px-1 pb-3 pt-1 backdrop-blur">
                    <div class="relative">
                        <x-ui.icon name="search" size="4" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-muted" />
                        <input type="search" x-model.debounce.150ms="q" class="field ps-9" placeholder="{{ __('orders.pos_search') }}" aria-label="{{ __('orders.pos_search') }}">
                    </div>
                    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist">
                        <button type="button" class="chip" role="tab" :aria-selected="cat === 0" :class="cat === 0 && 'chip-active'" x-on:click="cat = 0">{{ __('orders.pos_all') }}</button>
                        <template x-for="c in menu" :key="c.id">
                            <button type="button" class="chip shrink-0" role="tab" :aria-selected="cat === c.id" :class="cat === c.id && 'chip-active'" x-on:click="cat = c.id" x-text="c.name"></button>
                        </template>
                    </div>
                </div>

                <p x-show="!menu.length" x-cloak class="py-10 text-center text-muted">{{ __('orders.pos_empty_menu') }}</p>
                <p x-show="menu.length && !visible.length" x-cloak class="py-10 text-center text-muted">{{ __('orders.pos_no_results') }}</p>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <template x-for="p in visible" :key="p.id">
                        <button type="button" class="card group flex flex-col overflow-hidden text-start transition enabled:hover:-translate-y-0.5 enabled:hover:shadow-md disabled:opacity-55" :disabled="!p.available" x-on:click="pick(p)">
                            <span class="relative block aspect-[4/3] w-full overflow-hidden bg-surface-2">
                                <img :src="p.image || p.art" alt="" loading="lazy" class="size-full object-cover transition group-enabled:group-hover:scale-105">
                                <span x-show="!p.available" class="absolute inset-0 grid place-items-center bg-ink-950/55 text-sm font-semibold text-white">{{ __('orders.pos_sold_out') }}</span>
                                <span x-show="qtyOf(p.id)" x-cloak class="tnum absolute end-2 top-2 grid min-w-6 place-items-center rounded-full bg-brand-500 px-1.5 py-0.5 text-xs font-bold text-[var(--on-brand)]" x-text="qtyOf(p.id)"></span>
                            </span>
                            <span class="flex flex-1 flex-col gap-1 p-3">
                                <span class="line-clamp-2 text-sm font-semibold leading-snug" x-text="p.name"></span>
                                <span class="tnum mt-auto text-sm text-muted" x-text="(p.variants.length ? '{{ __('customer.from_price') }} ' : '') + money(p.price)"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </section>

            {{-- Order panel: a column on desktop, a bottom drawer on phones --}}
            <aside class="lg:sticky lg:top-4" :class="drawer ? 'fixed inset-0 z-40 flex flex-col justify-end bg-ink-950/50 lg:static lg:block lg:bg-transparent' : 'hidden lg:block'" x-on:click.self="drawer = false">
                <div class="card flex max-h-[92dvh] flex-col overflow-hidden rounded-b-none lg:max-h-[calc(100dvh-2rem)] lg:rounded-b-[inherit]" role="region" aria-label="{{ __('orders.pos_cart') }}">
                    <div class="flex items-center justify-between border-b border-line px-4 py-3">
                        <h2 class="display text-lg font-bold">{{ __('orders.pos_cart') }}</h2>
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-sm text-muted underline-offset-2 hover:underline" x-show="lines.length" x-on:click="lines = []">{{ __('orders.pos_clear') }}</button>
                            <button type="button" class="btn btn-secondary btn-sm lg:hidden" x-on:click="drawer = false" aria-label="{{ __('admin.close') }}"><x-ui.icon name="x" size="4" /></button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-3">
                        <div class="grid grid-cols-3 gap-1 rounded-xl bg-surface-2 p-1" role="radiogroup">
                            @foreach (['dine_in', 'takeaway', 'delivery'] as $type)
                                <button type="button" role="radio" :aria-checked="type === '{{ $type }}'" class="rounded-lg px-2 py-2 text-sm font-medium transition" :class="type === '{{ $type }}' ? 'bg-surface shadow-sm' : 'text-muted'" x-on:click="type = '{{ $type }}'">{{ __('orders.type_'.$type) }}</button>
                            @endforeach
                        </div>

                        <div x-show="type === 'dine_in'">
                            <label for="pos-table" class="mb-1.5 block text-sm font-medium">{{ __('orders.pos_table') }}</label>
                            <select id="pos-table" x-model="tableId" class="field">
                                <option value="">{{ __('orders.pos_choose_table') }}</option>
                                <template x-for="t in tables" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                            </select>
                            <p x-show="!tables.length" class="mt-1.5 text-xs text-muted">{{ __('orders.pos_no_tables') }}</p>
                        </div>
                        <div x-show="type !== 'dine_in'" x-cloak class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <input class="field" x-model="name" maxlength="80" placeholder="{{ __('orders.pos_customer_name') }}" aria-label="{{ __('orders.pos_customer_name') }}" autocomplete="off">
                            <input class="field" x-model="phone" maxlength="40" inputmode="tel" placeholder="{{ __('orders.pos_customer_phone') }}" aria-label="{{ __('orders.pos_customer_phone') }}" autocomplete="off" dir="ltr">
                            <input x-show="type === 'delivery'" class="field sm:col-span-2 lg:col-span-1" x-model="address" maxlength="255" placeholder="{{ __('orders.pos_address') }}" aria-label="{{ __('orders.pos_address') }}" autocomplete="off">
                        </div>

                        <p x-show="!lines.length" class="rounded-xl border border-dashed border-line-strong px-4 py-6 text-center text-sm text-muted">{{ __('orders.pos_cart_empty') }}</p>
                        <ul class="divide-y divide-line">
                            <template x-for="(l, i) in lines" :key="l.key">
                                <li class="py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold" x-text="l.name"></p>
                                            <p class="text-xs text-muted" x-show="l.optionNames.length" x-text="l.optionNames.join(', ')"></p>
                                            <p class="text-xs italic text-muted" x-show="l.note" x-text="l.note"></p>
                                        </div>
                                        <p class="tnum shrink-0 text-sm font-semibold" x-text="money(l.unit * l.qty)"></p>
                                    </div>
                                    <div class="mt-2 inline-flex items-center rounded-lg border border-line-strong">
                                        <button type="button" class="grid size-9 place-items-center text-lg" x-on:click="bump(i, -1)" aria-label="−">−</button>
                                        <span class="tnum min-w-8 text-center text-sm font-semibold" x-text="l.qty"></span>
                                        <button type="button" class="grid size-9 place-items-center text-lg" x-on:click="bump(i, 1)" aria-label="+">+</button>
                                    </div>
                                </li>
                            </template>
                        </ul>

                        <textarea class="field" rows="2" x-model="orderNote" maxlength="300" placeholder="{{ __('orders.pos_order_note') }}" aria-label="{{ __('orders.pos_order_note') }}"></textarea>

                        <div x-show="canPay" class="flex flex-wrap items-center gap-3">
                            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" class="size-4 accent-[var(--color-accent-600)]" x-model="paid"> {{ __('orders.pos_paid_now') }}</label>
                            <select x-show="paid" x-cloak x-model="method" class="field !w-auto !py-1.5" aria-label="{{ __('orders.pos_pay_with') }}">
                                <option value="cash">{{ __('orders.pay_cash') }}</option><option value="card">{{ __('orders.pay_card') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-line bg-surface px-4 py-4">
                        <p x-show="error" x-cloak class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/60 dark:text-red-200" role="alert" x-text="error"></p>
                        <div class="flex items-baseline justify-between"><span class="text-sm text-muted">{{ __('orders.pos_total') }}</span><span class="display tnum text-2xl font-bold" x-text="money(subtotal)"></span></div>
                        <p class="text-xs text-muted">{{ __('orders.pos_fees_note') }}</p>
                        <button type="button" class="btn btn-primary btn-lg w-full" :disabled="!lines.length || busy" x-on:click="send()"><span x-text="busy ? @js(__('orders.pos_sending')) : @js(__('orders.pos_send'))"></span></button>
                    </div>
                </div>
            </aside>
        </div>

        {{-- Mobile order bar --}}
        <div class="fixed inset-x-0 bottom-0 z-30 p-3 lg:hidden" x-show="lines.length && !drawer" x-cloak>
            <button type="button" class="btn btn-primary btn-lg w-full justify-between shadow-lg" x-on:click="drawer = true">
                <span class="tnum" x-text="count + ' ×'"></span><span>{{ __('orders.pos_cart') }}</span><span class="tnum" x-text="money(subtotal)"></span>
            </button>
        </div>

        {{-- Options sheet --}}
        <div x-show="sheet" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-ink-950/55 p-0 sm:items-center sm:p-4" x-on:click.self="sheet = null" role="dialog" aria-modal="true" :aria-label="sheet?.product.name">
            <div class="card flex max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-b-none sm:rounded-b-[inherit]" x-trap.noscroll="!!sheet">
                <template x-if="sheet">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                            <div><h3 class="display text-xl font-bold" x-text="sheet.product.name"></h3><p class="tnum text-sm text-muted" x-text="money(sheet.product.price)"></p></div>
                            <button type="button" class="btn btn-secondary btn-sm" x-on:click="sheet = null" aria-label="{{ __('admin.close') }}"><x-ui.icon name="x" size="4" /></button>
                        </div>
                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
                            <fieldset x-show="(sheet.product.variants || []).length">
                                <legend class="mb-2 flex w-full items-center justify-between text-sm font-semibold"><span>{{ __('customer.choose_size') }}</span><span class="text-xs font-normal text-muted">{{ __('orders.pos_required') }}</span></legend>
                                <div class="space-y-1.5">
                                    <template x-for="v in (sheet.product.variants || [])" :key="v.id">
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong px-3 py-2.5 has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 dark:has-[:checked]:bg-accent-900/20" :class="v.available ? '' : 'opacity-50'">
                                            <input type="radio" class="size-4 accent-[var(--color-accent-600)]" name="pos-size" :checked="sheet.variant === v.id" :disabled="!v.available" x-on:change="sheet.variant = v.id">
                                            <span class="flex-1 text-sm" x-text="v.name"></span><span class="tnum text-sm text-muted" x-text="money(v.price)"></span>
                                        </label>
                                    </template>
                                </div>
                            </fieldset>
                            <template x-for="slot in (sheet.product.combo || [])" :key="'s' + slot.id">
                                <fieldset>
                                    <legend class="mb-2 flex w-full items-center justify-between text-sm font-semibold"><span x-text="slot.name"></span><span class="text-xs font-normal text-muted">{{ __('orders.pos_required') }}</span></legend>
                                    <div class="space-y-1.5">
                                        <template x-for="it in slot.items" :key="it.id">
                                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong px-3 py-2.5 has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 dark:has-[:checked]:bg-accent-900/20" :class="it.available ? '' : 'opacity-50'">
                                                <input type="radio" class="size-4 accent-[var(--color-accent-600)]" :name="'pos-slot' + slot.id" :checked="sheet.combo[slot.id] === it.id" :disabled="!it.available" x-on:change="sheet.combo[slot.id] = it.id">
                                                <span class="flex-1 text-sm" x-text="it.name"></span><span class="tnum text-sm text-muted" x-text="it.delta > 0 ? '+' + money(it.delta) : ''"></span>
                                            </label>
                                        </template>
                                    </div>
                                </fieldset>
                            </template>
                            <template x-for="g in sheet.product.option_groups" :key="g.id">
                                <fieldset>
                                    <legend class="mb-2 flex w-full items-center justify-between text-sm font-semibold"><span x-text="g.name"></span>
                                        <span class="text-xs font-normal text-muted" x-text="(g.required ? t.required : t.optional) + (g.type === 'multiple' && g.max_select ? ' · ' + t.upTo.replace(':count', g.max_select) : '')"></span></legend>
                                    <div class="space-y-1.5">
                                        <template x-for="o in g.options" :key="o.id">
                                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong px-3 py-2.5 has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 dark:has-[:checked]:bg-accent-900/20">
                                                <input :type="g.type === 'single' ? 'radio' : 'checkbox'" class="size-4 accent-[var(--color-accent-600)]" :name="'g' + g.id" :checked="sheet.chosen[g.id].includes(o.id)" x-on:change="toggle(g, o)">
                                                <span class="flex-1 text-sm" x-text="o.name"></span>
                                                <span class="tnum text-sm text-muted" x-show="o.price_delta" x-text="(o.price_delta > 0 ? '+' : '') + money(o.price_delta)"></span>
                                            </label>
                                        </template>
                                    </div>
                                </fieldset>
                            </template>
                            <div><label class="mb-1.5 block text-sm font-medium">{{ __('orders.pos_note') }}</label><input class="field" x-model="sheet.note" maxlength="200" autocomplete="off"></div>
                        </div>
                        <div class="border-t border-line px-5 py-4">
                            <button type="button" class="btn btn-primary btn-lg w-full justify-between" :disabled="!sheetValid" x-on:click="commit()"><span>{{ __('orders.pos_add_to_order') }}</span><span class="tnum" x-text="money(sheetUnit)"></span></button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Sent --}}
        <div x-show="done" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-ink-950/55 p-4" role="alertdialog" aria-modal="true">
            <div class="card w-full max-w-sm space-y-4 p-6 text-center">
                <div class="mx-auto grid size-14 place-items-center rounded-full bg-accent-100 text-accent-700 dark:bg-accent-900/50 dark:text-accent-200"><x-ui.icon name="check" size="6" /></div>
                <p class="display text-xl font-bold" x-text="done ? t.sent.replace(':number', '#' + done.number) : ''"></p>
                <div class="grid gap-2"><a class="btn btn-primary" :href="done?.url">{{ __('orders.pos_view_order') }}</a><button type="button" class="btn btn-secondary" x-on:click="reset()">{{ __('orders.pos_another') }}</button></div>
            </div>
        </div>
    </div>
</x-layouts.app>
