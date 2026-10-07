// Customer menu: browsing, filtering, product options and the cart.
// Prices shown here are for convenience only; the server re-prices the cart (see CartPricing),
// and the order flow never trusts a price sent by the browser.

const fold = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('storefront', (cfg) => ({
        tree: cfg.tree,
        q: '',
        diet: [],
        avoid: [],
        filtersOpen: false,
        active: cfg.tree[0]?.id ?? null,
        sheet: null,
        cartOpen: false,
        cart: [],
        quoted: null,
        quoting: false,
        offline: false,
        timer: null,
        toast: '',
        bumped: false,
        stage: 'cart',
        form: { type: '', name: '', phone: '', address: '', note: '', table_id: '', payment: '' },
        errors: {},
        formError: '',
        submitting: false,
        key: '',
        totals: null,
        ord: cfg.ordering,
        cfg_diet: cfg.diet,
        cfg_allergen: cfg.allergen,

        init() {
            this.cart = this.load();
            this.prune();
            this.$watch('cart', () => { this.save(); this.requote(); });
            this.$watch('form.type', () => { this.errors = {}; this.requote(0); });
            this.$watch('cartOpen', (v) => { if (!v) { this.stage = 'cart'; } });
            this.resetForm();
            this.$watch('sheet', (v) => this.lock(v !== null || this.cartOpen));
            this.$watch('cartOpen', (v) => this.lock(v || this.sheet !== null));
            this.$nextTick(() => this.observe());
            if (this.cart.length) { this.requote(0); }
        },

        // ---- browsing -------------------------------------------------------------------
        get hasFilters() { return this.q.trim() !== '' || this.diet.length > 0 || this.avoid.length > 0; },
        get visible() {
            const q = fold(this.q.trim());
            return this.tree
                .map((c) => ({ ...c, products: c.products.filter((p) => this.matches(p, q)) }))
                .filter((c) => c.products.length > 0);
        },
        matches(p, q) {
            if (q && !fold(p.name).includes(q) && !fold(p.description).includes(q)) { return false; }
            if (this.diet.some((d) => !p.dietary.includes(d))) { return false; }
            if (this.avoid.some((a) => p.allergens.includes(a))) { return false; }
            return true;
        },
        resetFilters() { this.q = ''; this.diet = []; this.avoid = []; },
        toggleIn(list, value) { const i = this[list].indexOf(value); i === -1 ? this[list].push(value) : this[list].splice(i, 1); },

        goTo(id) {
            this.active = id;
            document.getElementById('cat-' + id)?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        },
        observe() {
            if (!('IntersectionObserver' in window)) { return; }
            const io = new IntersectionObserver((entries) => {
                entries.filter((e) => e.isIntersecting).forEach((e) => {
                    this.active = Number(e.target.dataset.cat);
                    document.querySelector('[data-tab="' + this.active + '"]')?.scrollIntoView({ inline: 'center', block: 'nearest' });
                });
            }, { rootMargin: '-96px 0px -70% 0px' });
            document.querySelectorAll('[data-cat]').forEach((el) => io.observe(el));
        },

        // ---- formatting ------------------------------------------------------------------
        money(cents) {
            const c = cfg.currency;
            let [int, frac] = (cents / 100).toFixed(c.decimals).split('.');
            int = int.replace(/\B(?=(\d{3})+(?!\d))/g, c.thousands);
            const num = frac ? int + c.decimal + frac : int;
            if (!c.symbol) { return num; }
            return c.after ? num + ' ' + c.symbol : c.symbol + num;
        },
        cents(p) { return Math.round(p * 100); },

        // ---- product sheet ---------------------------------------------------------------
        open(product, edit = null) {
            const selected = {};
            product.option_groups.forEach((g) => { selected[g.id] = g.options.filter((o) => o.default).map((o) => o.id); });
            if (edit) { Object.keys(selected).forEach((gid) => { selected[gid] = edit.options.filter((id) => product.option_groups.find((g) => g.id === Number(gid))?.options.some((o) => o.id === id)); }); }
            this.sheet = { product, selected, qty: edit?.qty ?? 1, note: edit?.note ?? '', editKey: edit?.key ?? null, showErrors: false };
            this.$nextTick(() => this.$refs.sheetClose?.focus());
        },
        quickAdd(product, event = null) {
            if (!product.available) { return; }
            if (product.option_groups.length) { this.open(product); return; }
            this.push({ product_id: product.id, options: [], qty: 1, note: '', name: product.name, labels: [], unit_cents: this.cents(product.price) });
            this.flash(product.name);
            this.fly(event?.currentTarget);
        },
        pic(p) { return p.image || p.art; },
        // A little dot flies from the tapped button to the cart bar: feedback that the dish was added.
        fly(from) {
            this.bumped = true;
            setTimeout(() => { this.bumped = false; }, 420);
            if (!from || matchMedia('(prefers-reduced-motion: reduce)').matches || !from.animate) { return; }
            const a = from.getBoundingClientRect();
            const bar = document.querySelector('[data-cart-bar]');
            const b = bar ? bar.getBoundingClientRect() : { left: innerWidth / 2, top: innerHeight - 40, width: 0, height: 0 };
            const dot = document.createElement('span');
            dot.style.cssText = 'position:fixed;z-index:70;width:16px;height:16px;border-radius:999px;pointer-events:none;background:var(--menu-accent);left:0;top:0';
            document.body.appendChild(dot);
            const x1 = a.left + a.width / 2 - 8, y1 = a.top + a.height / 2 - 8;
            const x2 = b.left + b.width / 2 - 8, y2 = b.top + b.height / 2 - 8;
            dot.animate([
                { transform: `translate(${x1}px, ${y1}px) scale(1)`, opacity: 1 },
                { transform: `translate(${(x1 + x2) / 2}px, ${Math.min(y1, y2) - 70}px) scale(1.15)`, opacity: 1, offset: 0.5 },
                { transform: `translate(${x2}px, ${y2}px) scale(.3)`, opacity: 0.2 },
            ], { duration: 560, easing: 'cubic-bezier(.3,.7,.4,1)' }).onfinish = () => dot.remove();
        },
        get featured() {
            return this.tree.flatMap((c) => c.products).filter((p) => p.featured && p.available).slice(0, 8);
        },
        // Dishes from other parts of the menu that go with what is in the cart. Only items that can be added in one tap.
        get suggestions() {
            const inCart = new Set(this.cart.map((l) => l.product_id));
            const catOf = new Map(this.tree.flatMap((c) => c.products.map((p) => [p.id, c.id])));
            const cartCats = new Set(this.cart.map((l) => catOf.get(l.product_id)));
            return this.tree
                .flatMap((c) => c.products.map((p) => ({ ...p, cat: c.id })))
                .filter((p) => p.available && !inCart.has(p.id) && !p.option_groups.some((g) => g.required))
                .sort((a, b) => (cartCats.has(a.cat) - cartCats.has(b.cat)) || (b.featured - a.featured) || (a.price - b.price))
                .slice(0, 4);
        },
        pick(group, option) {
            const list = this.sheet.selected[group.id];
            const has = list.includes(option.id);
            if (group.type === 'single') { this.sheet.selected[group.id] = has && !group.required ? [] : [option.id]; return; }
            if (has) { this.sheet.selected[group.id] = list.filter((id) => id !== option.id); }
            else if (group.max_select === null || list.length < group.max_select) { list.push(option.id); }
        },
        isPicked(group, option) { return this.sheet.selected[group.id].includes(option.id); },
        groupMissing(group) { return group.required && this.sheet.selected[group.id].length === 0; },
        get sheetUnit() {
            const s = this.sheet;
            return s.product.option_groups.reduce((sum, g) => sum + g.options.filter((o) => s.selected[g.id].includes(o.id)).reduce((a, o) => a + Math.round(o.price_delta * 100), 0), this.cents(s.product.price));
        },
        get sheetValid() { return this.sheet.product.option_groups.every((g) => !this.groupMissing(g)); },
        step(delta) { this.sheet.qty = Math.min(50, Math.max(1, this.sheet.qty + delta)); },
        submitSheet() {
            const s = this.sheet;
            if (!this.sheetValid) { s.showErrors = true; return; }
            const options = s.product.option_groups.flatMap((g) => s.selected[g.id]);
            const labels = s.product.option_groups.flatMap((g) => g.options.filter((o) => s.selected[g.id].includes(o.id)).map((o) => o.name));
            const line = { product_id: s.product.id, options, qty: s.qty, note: s.note.trim(), name: s.product.name, labels, unit_cents: Math.max(0, this.sheetUnit) };
            if (s.editKey) { this.cart = this.cart.filter((l) => l.key !== s.editKey); }
            this.push(line);
            this.sheet = null;
            this.flash(line.name);
            this.fly(null);
        },

        // ---- cart ------------------------------------------------------------------------
        key(l) { return [l.product_id, [...l.options].sort((a, b) => a - b).join(','), l.note].join('|'); },
        push(line) {
            line.key = this.key(line);
            const same = this.cart.find((l) => l.key === line.key);
            if (same) { same.qty = Math.min(50, same.qty + line.qty); this.cart = [...this.cart]; }
            else { this.cart = [...this.cart, line]; }
        },
        bump(line, delta) {
            line.qty = Math.min(50, Math.max(0, line.qty + delta));
            this.cart = this.cart.filter((l) => l.qty > 0);
        },
        remove(line) { this.cart = this.cart.filter((l) => l !== line); if (!this.cart.length) { this.cartOpen = false; } },
        editLine(line) {
            const product = this.tree.flatMap((c) => c.products).find((p) => p.id === line.product_id);
            if (!product) { return; }
            this.cartOpen = false;
            this.open(product, line);
        },
        get count() { return this.cart.reduce((n, l) => n + l.qty, 0); },
        // Server-confirmed line when available, otherwise the browser's own estimate.
        view(line, i) {
            const q = this.quoted?.lines?.[i];
            return q && q.product_id === line.product_id
                ? { total: q.total, unit: q.unit, errors: q.errors }
                : { total: this.money(line.unit_cents * line.qty), unit: this.money(line.unit_cents), errors: [] };
        },
        get subtotal() { return this.quoted && !this.offline ? this.quoted.subtotal : this.money(this.cart.reduce((s, l) => s + l.unit_cents * l.qty, 0)); },
        get hasErrors() { return (this.quoted?.lines || []).some((l) => l.errors.length); },
        errorText(codes) { return codes.map((c) => cfg.t['error_' + c] || c).join(' '); },

        prune() {
            const ids = new Set(this.tree.flatMap((c) => c.products.map((p) => p.id)));
            this.cart = this.cart.filter((l) => ids.has(l.product_id));
        },
        requote(delay = 300) {
            clearTimeout(this.timer);
            if (!this.cart.length) { this.quoted = null; return; }
            this.quoting = true;
            this.timer = setTimeout(async () => {
                try {
                    const res = await fetch(cfg.quoteUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify({ lines: this.cart.map((l) => ({ product_id: l.product_id, options: l.options, qty: l.qty, note: l.note })), type: this.stage === 'checkout' ? this.form.type : undefined }),
                    });
                    if (!res.ok) { throw new Error(String(res.status)); }
                    this.quoted = await res.json();
                    this.totals = this.quoted.totals || null;
                    this.offline = false;
                } catch (e) { this.offline = true; }
                this.quoting = false;
            }, delay);
        },

        load() {
            try { const raw = JSON.parse(localStorage.getItem(cfg.storageKey) || '[]'); return Array.isArray(raw) ? raw.filter((l) => l && l.product_id && Array.isArray(l.options)) : []; } catch (e) { return []; }
        },
        save() { try { localStorage.setItem(cfg.storageKey, JSON.stringify(this.cart)); } catch (e) { /* private mode */ } },

        // ---- checkout --------------------------------------------------------------------
        resetForm() {
            let guest = {};
            try { guest = JSON.parse(localStorage.getItem('qrmenu.guest') || '{}'); } catch (e) { /* none saved */ }
            this.form.type = this.ord.table && this.ord.types.includes('dine_in') ? 'dine_in' : (this.ord.types[0] || '');
            this.form.payment = this.ord.payments[0] || '';
            this.form.name = guest.name || '';
            this.form.phone = guest.phone || '';
            this.form.address = guest.address || '';
            this.form.table_id = this.ord.table ? this.ord.table.id : (this.ord.tables[0]?.id || '');
        },
        startCheckout() {
            if (!this.ord.accepting || this.hasErrors || !this.cart.length) { return; }
            this.stage = 'checkout';
            this.formError = '';
            this.errors = {};
            this.key = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(36).slice(2)).replace(/-/g, '');
            this.requote(0);
        },
        get needsContact() { return this.form.type !== 'dine_in'; },
        validateForm() {
            const e = {};
            const f = this.form;
            if (f.type === 'dine_in' && !this.ord.table && !f.table_id) { e.table_id = cfg.t.table_required; }
            if ((this.needsContact || this.ord.requireName) && !f.name.trim()) { e.name = cfg.t.name_required; }
            if (this.needsContact && !/^[0-9+()\-\s.]{6,40}$/.test(f.phone.trim())) { e.phone = cfg.t.phone_required; }
            if (f.type === 'delivery' && f.address.trim().length < 5) { e.address = cfg.t.address_required; }
            this.errors = e;
            return Object.keys(e).length === 0;
        },
        async submit() {
            if (this.submitting || !this.validateForm()) { return; }
            this.submitting = true;
            this.formError = '';
            const f = this.form;
            try {
                const res = await fetch(this.ord.orderUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify({
                        type: f.type, table_id: f.type === 'dine_in' ? (this.ord.table?.id || f.table_id || null) : null,
                        customer_name: f.name, customer_phone: f.phone, delivery_address: f.type === 'delivery' ? f.address : null,
                        note: f.note, payment_method: f.payment, idempotency_key: this.key,
                        lines: this.cart.map((l) => ({ product_id: l.product_id, options: l.options, qty: l.qty, note: l.note })),
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 201) {
                    try { localStorage.setItem('qrmenu.guest', JSON.stringify({ name: f.name, phone: f.phone, address: f.address })); localStorage.removeItem(cfg.storageKey); } catch (e) { /* ignore */ }
                    this.cart = [];
                    window.location.href = data.url;
                    return;
                }
                this.formError = res.status === 429 ? cfg.t.too_many : (data.message || cfg.t.generic_error);
                if (data.lines?.length) { this.quoted = { ...(this.quoted || {}), lines: data.lines }; this.stage = 'cart'; }
            } catch (e) {
                this.formError = cfg.t.generic_error;
            }
            this.submitting = false;
        },

        // ---- chrome ----------------------------------------------------------------------
        lock(on) { document.documentElement.classList.toggle('overflow-hidden', on); },
        closeAll() { this.sheet = null; this.cartOpen = false; this.filtersOpen = false; },
        flash(text) { this.toast = text; clearTimeout(this._t); this._t = setTimeout(() => { this.toast = ''; }, 1800); },
        trap(e) {
            const root = e.currentTarget;
            const items = [...root.querySelectorAll('button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])')].filter((el) => !el.disabled && el.offsetParent !== null);
            if (!items.length) { return; }
            const first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        },
    }));
});
