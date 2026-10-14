<x-layouts.app :title="__('chat.order_chat', ['number' => $order->number])">
    <x-ui.page-header :title="__('chat.order_chat', ['number' => $order->number])" :description="trim(($order->customer_name ?: '').' '.($order->table_name ? '· '.table_label($order->table_name) : ''))">
        <x-slot:actions><a href="{{ route('orders.show', $order->id) }}" class="btn btn-secondary">{{ __('chat.view_order') }}</a></x-slot:actions>
    </x-ui.page-header>

    <div class="card max-w-2xl p-4" x-data="{
        msgs: @js($messages), last: {{ collect($messages)->max('id') ?? 0 }}, text: '', sending: false, error: '',
        init() { this.$nextTick(() => this.scroll()); setInterval(() => { if (!document.hidden) this.poll(); }, 5000); },
        scroll() { const b = this.$refs.box; if (b) b.scrollTop = b.scrollHeight; },
        async poll() { try { const r = await fetch(@js(route('chat.messages', $order->id)) + '?after=' + this.last, { headers: { Accept: 'application/json' } }); if (!r.ok) return; const d = await r.json(); d.messages.forEach((m) => { this.msgs.push(m); this.last = Math.max(this.last, m.id); }); if (d.messages.length) this.$nextTick(() => this.scroll()); } catch (e) {} },
        async send() {
            const body = this.text.trim(); if (!body || this.sending) return; this.sending = true; this.error = '';
            try { const r = await fetch(@js(route('chat.reply', $order->id)), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ body }) });
                if (!r.ok) { this.error = @js(__('chat.send_failed')); } else { const d = await r.json(); if (d.message) { this.msgs.push(d.message); this.last = Math.max(this.last, d.message.id); } this.text = ''; this.$nextTick(() => this.scroll()); } } catch (e) { this.error = @js(__('chat.send_failed')); }
            this.sending = false;
        },
        time(iso) { return new Date(iso).toLocaleString([], { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' }); }
    }">
        <ul x-ref="box" class="max-h-[60vh] min-h-40 space-y-2 overflow-y-auto" aria-live="polite">
            <template x-for="m in msgs" :key="m.id">
                <li :class="m.from === 'staff' ? 'ms-10 text-end' : 'me-10'">
                    <span class="inline-block max-w-full whitespace-pre-wrap break-words rounded-2xl px-3 py-2 text-sm" :class="m.from === 'staff' ? 'bg-brand text-white' : 'bg-surface-2'" x-text="m.text"></span>
                    <span class="mt-0.5 block text-[11px] text-muted" x-text="time(m.at)"></span>
                </li>
            </template>
        </ul>
        @unless ($open)<p class="mt-3 text-sm text-muted">{{ __('chat.closed_staff') }}</p>@endunless
        <form @submit.prevent="send()" class="mt-3 flex gap-2">
            <input type="text" x-model="text" maxlength="500" class="input min-w-0 flex-1" placeholder="{{ __('chat.reply_placeholder') }}" aria-label="{{ __('chat.reply_placeholder') }}" autocomplete="off">
            <x-ui.button :block="false" ::disabled="sending || !text.trim()">{{ __('chat.send') }}</x-ui.button>
        </form>
        <p class="mt-2 text-sm font-medium text-red-600" x-show="error" x-text="error" role="alert" x-cloak></p>
    </div>
</x-layouts.app>
