{{-- Guest chat box for the order page. $chatUrl: GET messages / POST message. Polls every few seconds while visible. --}}
<section class="menu-card p-4" x-data="{
    open: false, text: '', sending: false, msgs: [], last: 0, unread: 0, closed: false, error: '', timer: null,
    url: @js($chatUrl), csrf: @js(csrf_token()),
    init() { this.poll(); this.timer = setInterval(() => { if (!document.hidden) this.poll(); }, 6000); },
    async poll() {
        try {
            const r = await fetch(this.url + '?after=' + this.last + (this.open ? '&seen=1' : ''), { headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            this.closed = !d.open;
            d.messages.forEach((m) => { this.msgs.push(m); this.last = Math.max(this.last, m.id); if (m.from === 'staff' && !this.open) this.unread++; });
            if (d.messages.length && this.open) this.$nextTick(() => this.scroll());
        } catch (e) {}
    },
    toggle() { this.open = !this.open; if (this.open) { this.unread = 0; this.poll(); this.$nextTick(() => this.scroll()); } },
    scroll() { const b = this.$refs.box; if (b) b.scrollTop = b.scrollHeight; },
    async send() {
        const body = this.text.trim(); if (!body || this.sending) return;
        this.sending = true; this.error = '';
        try {
            const r = await fetch(this.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf }, body: JSON.stringify({ body }) });
            if (!r.ok) { this.error = @js(__('chat.send_failed')); } else { const d = await r.json(); if (d.message) { this.msgs.push(d.message); this.last = Math.max(this.last, d.message.id); } this.text = ''; this.$nextTick(() => this.scroll()); }
        } catch (e) { this.error = @js(__('chat.send_failed')); }
        this.sending = false;
    },
    time(iso) { return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
}" x-on:beforeunload.window="clearInterval(timer)">
    <button type="button" class="flex w-full items-center justify-between gap-3 text-start font-semibold" @click="toggle()" :aria-expanded="open">
        <span>{{ __('chat.guest_title') }}</span>
        <span class="flex items-center gap-2">
            <span class="menu-accent rounded-full px-2 py-0.5 text-xs font-bold" x-show="unread > 0" x-text="unread" x-cloak></span>
            <span aria-hidden="true" x-text="open ? '−' : '+'"></span>
        </span>
    </button>
    <div x-show="open" x-cloak class="mt-3 space-y-3">
        <p class="menu-muted text-sm" x-show="msgs.length === 0">{{ __('chat.guest_hint') }}</p>
        <ul x-ref="box" class="max-h-64 space-y-2 overflow-y-auto" aria-live="polite">
            <template x-for="m in msgs" :key="m.id">
                <li :class="m.from === 'guest' ? 'ms-8 text-end' : 'me-8'">
                    <span class="inline-block max-w-full whitespace-pre-wrap break-words rounded-2xl px-3 py-2 text-sm" :class="m.from === 'guest' ? 'menu-accent' : 'menu-surface border menu-line'" x-text="m.text"></span>
                    <span class="menu-muted mt-0.5 block text-[11px]"><span x-show="m.from === 'staff'">{{ __('chat.staff') }} · </span><span x-text="time(m.at)"></span></span>
                </li>
            </template>
        </ul>
        <p class="menu-muted text-sm" x-show="closed" x-cloak>{{ __('chat.closed') }}</p>
        <form x-show="!closed" @submit.prevent="send()" class="flex gap-2">
            <input type="text" x-model="text" maxlength="500" class="menu-card min-w-0 flex-1 px-3 py-2.5" placeholder="{{ __('chat.placeholder') }}" aria-label="{{ __('chat.placeholder') }}" autocomplete="off">
            <button class="menu-btn !px-4" :disabled="sending || !text.trim()">{{ __('chat.send') }}</button>
        </form>
        <p class="text-sm font-medium text-red-600" x-show="error" x-text="error" role="alert" x-cloak></p>
    </div>
</section>
