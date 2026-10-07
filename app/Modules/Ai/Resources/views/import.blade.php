<x-layouts.app :title="__('ai.import_title')">
    <x-ui.page-header :title="__('ai.import_title')" :description="__('ai.import_sub')" :back="['url' => route('menu.index'), 'label' => __('menu.title')]" />

    @unless ($enabled)
        <div class="card"><x-ui.empty icon="sparkles" :title="__('ai.import_not_configured_title')" :text="__('ai.error_not_configured')" /></div>
    @else
        <div x-data="menuImport(@js(['preview' => route('ai.import.preview'), 'commit' => route('ai.import.commit'), 'csrf' => csrf_token(), 'max' => $maxChars, 'failText' => __('ai.error_provider_error'), 'doneText' => __('ai.import_done'), 'foundText' => __('ai.import_found')]))" class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                {{-- Step 1: paste --}}
                <x-ui.card x-show="!result">
                    <label for="menu-text" class="mb-1.5 block text-sm font-medium">{{ __('ai.import_paste') }}</label>
                    <textarea id="menu-text" x-model="text" rows="14" class="field font-mono text-[13px]" placeholder="{{ __('ai.import_placeholder') }}" :maxlength="max * 2"></textarea>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <span class="tnum text-xs" :class="text.length > max ? 'text-red-600' : 'text-muted'" x-text="@js(__('ai.import_chars', ['count' => '__', 'max' => $maxChars])).replace('__', text.length)"></span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-muted">{{ __('ai.import_cost', ['count' => $cost]) }}</span>
                            <button type="button" class="btn btn-primary" x-on:click="read()" :disabled="busy || text.trim().length < 10"><x-ui.icon name="sparkles" size="4" /><span x-text="busy ? @js(__('ai.writing')) : @js(__('ai.import_read'))"></span></button>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-red-600 dark:text-red-400" x-show="error" x-text="error" role="alert"></p>
                </x-ui.card>

                {{-- Step 2: review and edit --}}
                <template x-if="result && !done">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-semibold" x-text="foundText.replace(':count', count)"></p>
                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="reset()">{{ __('ai.import_again') }}</button>
                        </div>
                        <p class="rounded-xl bg-brand-100 px-4 py-2.5 text-sm text-brand-900 dark:bg-brand-900/40 dark:text-brand-100" x-show="missingPrices > 0" x-text="@js(__('ai.import_no_price'))"></p>
                        <template x-for="(cat, ci) in result.categories" :key="ci">
                            <x-ui.card>
                                <div class="mb-3 flex items-center gap-3">
                                    <div class="min-w-0 flex-1"><label class="mb-1 block text-xs font-medium text-muted">{{ __('ai.category') }}</label><input x-model="cat.name" maxlength="120" class="field font-semibold"></div>
                                </div>
                                <ul class="divide-y divide-line">
                                    <template x-for="(item, ii) in cat.items" :key="ii">
                                        <li class="grid gap-2 py-3 sm:grid-cols-[1fr_7rem_auto]">
                                            <div class="space-y-1.5"><input x-model="item.name" maxlength="160" class="field" aria-label="{{ __('ai.dish') }}"><input x-model="item.description" maxlength="500" class="field !py-1.5 text-sm" placeholder="{{ __('ai.description') }}" aria-label="{{ __('ai.description') }}"></div>
                                            <input x-model="item.price" type="number" step="0.01" min="0" inputmode="decimal" class="field tnum" :class="item.price === null || item.price === '' ? 'border-brand-500' : ''" aria-label="{{ __('ai.price') }}">
                                            <button type="button" class="btn btn-ghost btn-sm text-red-600 dark:text-red-400" x-on:click="cat.items.splice(ii, 1); prune()" aria-label="{{ __('ai.import_remove') }}"><x-ui.icon name="trash" size="4" /></button>
                                        </li>
                                    </template>
                                </ul>
                            </x-ui.card>
                        </template>
                        <p class="text-sm text-red-600 dark:text-red-400" x-show="error" x-text="error" role="alert"></p>
                        <div class="flex justify-end"><button type="button" class="btn btn-primary btn-lg" x-on:click="commit()" :disabled="busy || count === 0"><span x-text="busy ? @js(__('ai.import_adding')) : @js(__('ai.import_add'))"></span></button></div>
                    </div>
                </template>

                <x-ui.card x-show="done" x-cloak>
                    <div class="text-center">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-accent-100 text-accent-800 dark:bg-accent-900/50 dark:text-accent-200"><x-ui.icon name="check" size="6" /></span>
                        <p class="mt-3 font-semibold" x-text="doneMessage"></p>
                        <a :href="url" class="btn btn-primary mt-5">{{ __('menu.title') }}</a>
                    </div>
                </x-ui.card>
            </div>

            <aside class="space-y-5">
                <x-ui.card :title="__('ai.import_how')">
                    <ol class="space-y-3 text-sm text-muted">
                        @foreach (['import_step1', 'import_step2', 'import_step3'] as $n => $step)
                            <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-surface-2 text-xs font-bold text-fg">{{ $n + 1 }}</span>{{ __('ai.'.$step) }}</li>
                        @endforeach
                    </ol>
                    <p class="mt-4 border-t border-line pt-3 text-xs text-muted">@if ($credits['remaining'] === null){{ __('ai.credits_unlimited') }}@else{{ __('ai.credits_left', ['count' => $credits['remaining']]) }}@endif</p>
                </x-ui.card>
            </aside>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('menuImport', (cfg) => ({
                    ...cfg, text: '', busy: false, error: '', result: null, done: false, doneMessage: '', url: '',
                    get count() { return this.result ? this.result.categories.reduce((n, c) => n + c.items.length, 0) : 0; },
                    get missingPrices() { return this.result ? this.result.categories.reduce((n, c) => n + c.items.filter((i) => i.price === null || i.price === '').length, 0) : 0; },
                    async post(url, body) {
                        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify(body) });
                        return { ok: res.ok, data: await res.json().catch(() => ({})) };
                    },
                    async read() {
                        this.busy = true; this.error = '';
                        try {
                            const { ok, data } = await this.post(cfg.preview, { text: this.text });
                            if (ok) { this.result = data; } else { this.error = data.message || data.errors?.text?.[0] || cfg.failText; }
                        } catch (e) { this.error = cfg.failText; }
                        this.busy = false;
                    },
                    prune() { this.result.categories = this.result.categories.filter((c) => c.items.length > 0); },
                    reset() { this.result = null; this.error = ''; },
                    async commit() {
                        this.busy = true; this.error = '';
                        try {
                            const { ok, data } = await this.post(cfg.commit, { categories: this.result.categories.map((c) => ({ name: c.name, items: c.items.map((i) => ({ name: i.name, description: i.description, price: i.price === '' ? null : i.price })) })) });
                            if (ok) { this.done = true; this.url = data.url; this.doneMessage = cfg.doneText.replace(':categories', data.categories).replace(':products', data.products).replace(':hidden', data.hidden); }
                            else { this.error = data.message || Object.values(data.errors || {})[0]?.[0] || cfg.failText; }
                        } catch (e) { this.error = cfg.failText; }
                        this.busy = false;
                    },
                }));
            });
        </script>
    @endunless
</x-layouts.app>
