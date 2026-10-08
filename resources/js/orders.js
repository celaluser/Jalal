// Live order board: polls the feed, rings on new orders, moves orders along with one tap.
// Works on any shared host: plain polling, no websockets needed.

document.addEventListener('alpine:init', () => {
    window.Alpine.data('orderBoard', (cfg) => ({
        orders: cfg.orders,
        accepting: cfg.accepting,
        offline: false,
        sound: (() => { try { return localStorage.getItem('orders.sound') !== 'off'; } catch (e) { return true; } })(),
        skew: Date.now() - Date.parse(cfg.now),
        tick: Date.now(),
        seen: new Set(cfg.orders.map((o) => o.id)),
        fresh: new Set(),
        busy: null,
        toast: '',
        ctx: null,
        timer: null,

        init() {
            this.schedule();
            setInterval(() => { this.tick = Date.now(); }, 15000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) { this.refresh(); } });
            this.title();
        },

        // ---- columns ---------------------------------------------------------------------
        get newOrders() { return this.orders.filter((o) => o.status === 'new'); },
        get kitchen() { return this.orders.filter((o) => ['accepted', 'preparing'].includes(o.status)); },
        get ready() { return this.orders.filter((o) => o.status === 'ready'); },
        get done() { return this.orders.filter((o) => ['completed', 'cancelled'].includes(o.status)).sort((a, b) => b.id - a.id).slice(0, 15); },
        get openCount() { return this.orders.filter((o) => ['new', 'accepted', 'preparing', 'ready'].includes(o.status)).length; },

        age(o) { return Math.max(0, Math.floor((this.tick - this.skew - Date.parse(o.created)) / 60000)); },
        late(o) { return ['new', 'accepted', 'preparing'].includes(o.status) && this.age(o) > (o.prep_minutes || 15) + 10; },
        ageText(o) { const m = this.age(o); return m < 1 ? cfg.t.just_now : cfg.t.minutes_ago.replace(':count', m); },
        label(o) { return o.forward === 'completed' ? cfg.t['action_completed_' + o.type] : cfg.t['action_' + o.forward]; },
        where(o) { return o.type === 'dine_in' ? (/^\d+$/.test(o.table || '') ? cfg.t.table.replace(':name', o.table) : (o.table || '?')) : (o.name || cfg.t.guest); },

        // ---- polling ---------------------------------------------------------------------
        schedule() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), document.hidden ? cfg.interval * 4 : cfg.interval);
        },
        async refresh() {
            try {
                const res = await fetch(cfg.feedUrl, { headers: { Accept: 'application/json' } });
                if (!res.ok) { throw new Error(String(res.status)); }
                const data = await res.json();
                this.offline = false;
                this.skew = Date.now() - Date.parse(data.now);
                this.accepting = data.accepting;
                const arrived = data.orders.filter((o) => !this.seen.has(o.id) && o.status === 'new');
                data.orders.forEach((o) => this.seen.add(o.id));
                this.orders = data.orders;
                if (arrived.length) { this.arrived(arrived); }
                this.title();
            } catch (e) { this.offline = true; }
            this.schedule();
        },
        arrived(list) {
            list.forEach((o) => this.fresh.add(o.id));
            setTimeout(() => list.forEach((o) => this.fresh.delete(o.id)), 12000);
            this.flash(cfg.t.new_order.replace(':number', '#' + list[0].number));
            this.beep();
            if (this.sound === 'notify' || (window.Notification && Notification.permission === 'granted' && document.hidden)) {
                try { new Notification(cfg.t.new_order.replace(':number', '#' + list[0].number)); } catch (e) { /* ignore */ }
            }
        },
        title() {
            const n = this.newOrders.length;
            document.title = (n ? '(' + n + ') ' : '') + cfg.title;
        },

        // ---- sound -----------------------------------------------------------------------
        toggleSound() {
            this.sound = !this.sound;
            try { localStorage.setItem('orders.sound', this.sound ? 'on' : 'off'); } catch (e) { /* ignore */ }
            if (this.sound) { this.beep(); if (window.Notification && Notification.permission === 'default') { Notification.requestPermission(); } }
        },
        beep() {
            if (!this.sound) { return; }
            try {
                this.ctx ??= new (window.AudioContext || window.webkitAudioContext)();
                [0, 0.22, 0.44].forEach((delay, i) => {
                    const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
                    osc.type = 'sine'; osc.frequency.value = i === 1 ? 988 : 784;
                    gain.gain.setValueAtTime(0.0001, this.ctx.currentTime + delay);
                    gain.gain.exponentialRampToValueAtTime(0.25, this.ctx.currentTime + delay + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, this.ctx.currentTime + delay + 0.2);
                    osc.connect(gain).connect(this.ctx.destination);
                    osc.start(this.ctx.currentTime + delay); osc.stop(this.ctx.currentTime + delay + 0.22);
                });
            } catch (e) { /* audio blocked until the first tap */ }
        },

        // ---- actions ---------------------------------------------------------------------
        async post(url, body) {
            const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify(body) });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { this.flash(data.message || 'Error'); }
            await this.refresh();
            return res.ok;
        },
        async move(o, status, reason = null) {
            if (this.busy) { return; }
            this.busy = o.id;
            await this.post(cfg.statusUrl + '/' + o.id + '/status', { status, reason });
            this.busy = null;
        },
        cancel(o) {
            if (!confirm(cfg.t.cancel_confirm)) { return; }
            const reason = prompt(cfg.t.cancel_reason) ?? '';
            this.move(o, 'cancelled', reason);
        },
        async pay(o, method) { await this.post(cfg.statusUrl + '/' + o.id + '/pay', { method }); },
        async togglePause() {
            const res = await fetch(cfg.pauseUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf } });
            if (res.ok) { this.accepting = (await res.json()).accepting; }
        },
        flash(text) { this.toast = text; clearTimeout(this._t); this._t = setTimeout(() => { this.toast = ''; }, 3500); },
    }));

    // Staff order entry: pick dishes, choose where it goes, send it to the kitchen.
    window.Alpine.data('posApp', (cfg) => ({
        menu: cfg.menu, tables: cfg.tables, canPay: cfg.canPay, t: cfg.t,
        cat: 0, q: '', lines: [], sheet: null, drawer: false, done: null, busy: false, error: '',
        type: 'dine_in', tableId: cfg.preselect && cfg.tables.some((t) => t.id === cfg.preselect) ? cfg.preselect : '', name: '', phone: '', address: '', orderNote: '', paid: false, method: 'cash',
        fmt: new Intl.NumberFormat(cfg.locale, { style: 'currency', currency: cfg.currency }),
        key: crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random(),

        money(v) { return this.fmt.format(v); },

        // ---- menu ------------------------------------------------------------------------
        get visible() {
            const q = this.q.trim().toLowerCase();
            return this.menu
                .filter((c) => this.cat === 0 || c.id === this.cat)
                .flatMap((c) => c.products)
                .filter((p) => !q || p.name.toLowerCase().includes(q));
        },
        qtyOf(id) { return this.lines.filter((l) => l.id === id).reduce((n, l) => n + l.qty, 0); },

        pick(p) {
            if (!p.available) { return; }
            const variants = (p.variants || []).filter((v) => v.available);
            if (!p.option_groups.length && !(p.variants || []).length && !(p.combo || []).length) { this.add(p, [], '', [], null, null); return; }
            const chosen = {};
            p.option_groups.forEach((g) => {
                // Required single-choice groups start on their default (or first) option, as on the guest menu.
                const def = g.options.filter((o) => o.default).map((o) => o.id);
                chosen[g.id] = def.length ? (g.type === 'single' ? def.slice(0, 1) : def) : (g.type === 'single' && g.required && g.options.length ? [g.options[0].id] : []);
            });
            const combo = {};
            (p.combo || []).forEach((slot) => { const first = slot.items.find((i) => i.available); combo[slot.id] = first ? first.id : null; });
            this.sheet = { product: p, chosen, note: '', combo, variant: variants.length ? variants[0].id : null };
        },
        toggle(g, o) {
            const cur = this.sheet.chosen[g.id];
            if (g.type === 'single') { this.sheet.chosen[g.id] = [o.id]; return; }
            if (cur.includes(o.id)) { this.sheet.chosen[g.id] = cur.filter((i) => i !== o.id); return; }
            if (g.max_select && cur.length >= g.max_select) { this.sheet.chosen[g.id] = [...cur.slice(1), o.id]; return; }
            this.sheet.chosen[g.id] = [...cur, o.id];
        },
        get sheetValid() {
            return !!this.sheet && !((this.sheet.product.variants || []).length > 0 && this.sheet.variant === null) && !(this.sheet.product.combo || []).some((slot) => !slot.items.find((i) => i.id === this.sheet.combo[slot.id] && i.available)) && this.sheet.product.option_groups.every((g) => !g.required || this.sheet.chosen[g.id].length > 0);
        },
        get sheetUnit() {
            if (!this.sheet) { return 0; }
            const v = (this.sheet.product.variants || []).find((x) => x.id === this.sheet.variant);
            const extra = (this.sheet.product.combo || []).reduce((n, slot) => n + ((slot.items.find((i) => i.id === this.sheet.combo[slot.id]) || {}).delta || 0), 0);
            return extra + (v ? v.price : this.sheet.product.price) + this.sheet.product.option_groups.reduce((n, g) => n + g.options.filter((o) => this.sheet.chosen[g.id].includes(o.id)).reduce((m, o) => m + o.price_delta, 0), 0);
        },
        commit() {
            if (!this.sheetValid) { return; }
            const p = this.sheet.product;
            const picked = p.option_groups.flatMap((g) => g.options.filter((o) => this.sheet.chosen[g.id].includes(o.id)));
            const v = (p.variants || []).find((x) => x.id === this.sheet.variant);
            this.add(p, picked.map((o) => o.id), this.sheet.note.trim(), picked, v || null, (p.combo || []).length ? { ...this.sheet.combo } : null);
            this.sheet = null;
        },

        // ---- order lines -----------------------------------------------------------------
        add(p, optionIds, note, picked, variant = null, combo = null) {
            const sig = p.id + ':' + (variant ? variant.id : 0) + ':' + JSON.stringify(combo || {}) + ':' + [...optionIds].sort().join(',') + ':' + note;
            const same = this.lines.find((l) => l.sig === sig);
            if (same) { same.qty = Math.min(same.qty + 1, 50); return; }
            const comboNames = combo ? (p.combo || []).map((slot) => (slot.items.find((i) => i.id === combo[slot.id]) || {}).name).filter(Boolean) : [];
            const comboExtra = combo ? (p.combo || []).reduce((n, slot) => n + ((slot.items.find((i) => i.id === combo[slot.id]) || {}).delta || 0), 0) : 0;
            const unit = comboExtra + (variant ? variant.price : p.price) + picked.reduce((n, o) => n + o.price_delta, 0);
            this.lines.push({ key: sig + ':' + Date.now(), sig, id: p.id, variant_id: variant ? variant.id : null, combo, name: p.name, options: optionIds, optionNames: [...(variant ? [variant.name] : []), ...comboNames, ...picked.map((o) => o.name)], note, qty: 1, unit });
        },
        bump(i, d) {
            const l = this.lines[i];
            l.qty = Math.min(l.qty + d, 50);
            if (l.qty < 1) { this.lines.splice(i, 1); }
        },
        get subtotal() { return this.lines.reduce((n, l) => n + l.unit * l.qty, 0); },
        get count() { return this.lines.reduce((n, l) => n + l.qty, 0); },

        // ---- sending ---------------------------------------------------------------------
        async send() {
            if (this.busy || !this.lines.length) { return; }
            this.busy = true; this.error = '';
            try {
                const res = await fetch(cfg.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        type: this.type, table_id: this.type === 'dine_in' ? (this.tableId || null) : null,
                        customer_name: this.name, customer_phone: this.phone, delivery_address: this.address, note: this.orderNote,
                        paid: this.canPay && this.paid, payment_method: this.method, idempotency_key: this.key,
                        lines: this.lines.map((l) => ({ product_id: l.id, variant_id: l.variant_id || null, combo: l.combo || undefined, qty: l.qty, options: l.options, note: l.note })),
                    }),
                });
                const body = await res.json().catch(() => ({}));
                if (res.ok) { this.done = body; this.drawer = false; return; }
                this.error = body.message || (body.errors ? Object.values(body.errors)[0][0] : this.t.failed);
            } catch (e) {
                this.error = this.t.failed;
            } finally {
                this.busy = false;
            }
        },
        reset() {
            Object.assign(this, { lines: [], done: null, error: '', tableId: '', name: '', phone: '', address: '', orderNote: '', paid: false, q: '' });
            this.key = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random();
        },
    }));
});
