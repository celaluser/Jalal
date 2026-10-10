// Customer menu: browsing, filtering, product options and the cart.
// Prices shown here are for convenience only; the server re-prices the cart (see CartPricing),
// and the order flow never trusts a price sent by the browser.

const fold = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('storefront', (cfg) => ({
        tree: cfg.tree,
        menus: cfg.menus || [],
        menuId: (cfg.menus && cfg.menus[0]) ? cfg.menus[0].id : 0,
        q: '',
        diet: [],
        avoid: [],
        filtersOpen: false,
        active: cfg.tree[0]?.id ?? null,
        sheet: null,
        cartOpen: false,
        cart: [],
        quoted: null,
        promoInput: '',
        quoting: false,
        offline: false,
        timer: null,
        toast: '',
        bumped: false,
        stage: 'cart',
        form: { type: '', name: '', phone: '', email: '', promo: '', marketing: false, address: '', note: '', table_id: '', payment: '', vehicle: '', room: '', zone: '', later: false, when: '', notify: '' },
        errors: {},
        formError: '',
        submitting: false,
        key: '',
        totals: null,
        ord: cfg.ordering,
        shown: cfg.scroll === 'infinite' ? 3 : 9999,
        alt: false,
        io: null,
        cfg_diet: cfg.diet,
        cfg_badge: cfg.badge,
        cfg_nutrient: cfg.nutrient,
        cfg_allergen: cfg.allergen,

        kioskDone: null,
        // Price and spice filters, sorting, favourites, comfort settings and a currency for browsing.
        maxPrice: Math.ceil(Math.max(0, ...cfg.tree.flatMap((c) => c.products).map((p) => Math.max(p.price, ...(p.variants || []).map((v) => v.price))))),
        priceCeil: Math.ceil(Math.max(0, ...cfg.tree.flatMap((c) => c.products).map((p) => Math.max(p.price, ...(p.variants || []).map((v) => v.price))))),
        spice: 0,
        favOnly: false,
        sort: 'menu',
        favs: [],
        a11y: { large: false, contrast: false, calm: false },
        curCode: (cfg.currencies.find((c) => c.base) || {}).code || '',
        banners: cfg.banners.filter((b) => !b.popup),
        popup: null,
        installEvent: null,
        tab: null,
        chat: { open: false, msgs: [], input: '', busy: false },

        init() {
            if (cfg.kiosk) { this.startKiosk(); }
            this.restorePrefs();
            this.initPwa();
            this.maybeReorder();
            this.showPopup();
            ['spice', 'favOnly', 'sort', 'curCode'].forEach((k) => this.$watch(k, () => this.savePrefs()));
            ['diet', 'avoid', 'favs', 'a11y'].forEach((k) => this.$watch(k, () => this.savePrefs(), { deep: true }));
            this.cart = this.load();
            this.prune();
            this.$watch('cart', () => { this.save(); this.requote(); });
            this.$watch('form.type', () => { this.errors = {}; this.requote(0); });
            this.$watch('form.zone', () => this.requote(0));
            this.$watch('cartOpen', (v) => { if (!v) { this.stage = 'cart'; } });
            this.resetForm();
            this.$watch('sheet', (v) => this.lock(v !== null || this.cartOpen));
            this.$watch('cartOpen', (v) => this.lock(v || this.sheet !== null));
            this.$nextTick(() => this.observe());
            this.$watch('shown', () => this.$nextTick(() => this.observe()));
            this.$watch('menuId', () => { this.shown = cfg.scroll === 'infinite' ? 3 : 9999; this.$nextTick(() => this.observe()); });
            this.watchSentinel();
            try { this.alt = localStorage.getItem('qrmenu.alt') === '1'; } catch (e) { /* private mode */ }
            document.documentElement.toggleAttribute('data-menu-alt', this.alt);
            if (this.cart.length) { this.requote(0); }
        },

        // ---- browsing -------------------------------------------------------------------
        get hasFilters() { return this.q.trim() !== '' || this.diet.length > 0 || this.avoid.length > 0 || this.spice > 0 || this.favOnly || this.maxPrice < this.priceCeil; },
        get filterCount() { return this.diet.length + this.avoid.length + (this.spice > 0 ? 1 : 0) + (this.favOnly ? 1 : 0) + (this.maxPrice < this.priceCeil ? 1 : 0); },
        get visible() {
            const q = fold(this.q.trim());
            // Several menus (breakfast, lunch...): show one at a time, but a search looks through all of them.
            const pool = this.menus.length && !this.hasFilters ? this.tree.filter((c) => c.menu_id === this.menuId) : this.tree;
            const order = { price_asc: (a, b) => this.lowest(a) - this.lowest(b), price_desc: (a, b) => this.lowest(b) - this.lowest(a) }[this.sort];
            const list = pool
                .map((c) => { const products = c.products.filter((p) => this.matches(p, q)); return { ...c, products: order ? [...products].sort(order) : products }; })
                .filter((c) => c.products.length > 0);
            // Long menus can load category by category while scrolling; a search always looks through everything.
            return this.hasFilters ? list : list.slice(0, this.shown);
        },
        get allVisibleShown() { return this.hasFilters || this.shown >= (this.menus.length ? this.tree.filter((c) => c.menu_id === this.menuId) : this.tree).length; },
        watchSentinel() {
            if (cfg.scroll !== 'infinite' || !('IntersectionObserver' in window)) { this.shown = 9999; return; }
            this.$nextTick(() => {
                const el = this.$refs.sentinel;
                if (!el) { return; }
                new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting) && !this.allVisibleShown) { this.shown += 3; } }, { rootMargin: '600px 0px' }).observe(el);
            });
        },
        toggleAlt() {
            this.alt = !this.alt;
            document.documentElement.toggleAttribute('data-menu-alt', this.alt);
            try { localStorage.setItem('qrmenu.alt', this.alt ? '1' : '0'); } catch (e) { /* ignore */ }
        },
        matches(p, q) {
            if (q && !fold(p.name).includes(q) && !fold(p.description).includes(q)) { return false; }
            if (this.diet.some((d) => !p.dietary.includes(d))) { return false; }
            if (this.avoid.some((a) => p.allergens.includes(a))) { return false; }
            if (this.spice > 0 && (p.spice || 0) < this.spice) { return false; }
            if (this.favOnly && !this.favs.includes(p.id)) { return false; }
            if (this.maxPrice < this.priceCeil && this.lowest(p) > this.maxPrice) { return false; }
            return true;
        },
        lowest(p) { return (p.variants || []).length ? Math.min(...p.variants.map((v) => v.price)) : p.price; },
        resetFilters() { this.q = ''; this.diet = []; this.avoid = []; this.spice = 0; this.favOnly = false; this.maxPrice = this.priceCeil; },

        // ---- favourites and remembered preferences (kept in this browser only) -----------
        isFav(p) { return this.favs.includes(p.id); },
        toggleFav(p) { const i = this.favs.indexOf(p.id); i === -1 ? this.favs.push(p.id) : this.favs.splice(i, 1); },
        restorePrefs() {
            try {
                const raw = JSON.parse(localStorage.getItem(cfg.prefsKey) || '{}');
                if (Array.isArray(raw.diet)) { this.diet = raw.diet.filter((d) => d in cfg.diet); }
                if (Array.isArray(raw.avoid)) { this.avoid = raw.avoid.filter((a) => a in cfg.allergen); }
                if (Array.isArray(raw.favs)) { const ids = new Set(cfg.tree.flatMap((c) => c.products).map((p) => p.id)); this.favs = raw.favs.filter((id) => ids.has(id)); }
                if ([0, 1, 2, 3].includes(raw.spice)) { this.spice = raw.spice; }
                if (['menu', 'price_asc', 'price_desc'].includes(raw.sort)) { this.sort = raw.sort; }
                if (raw.a11y && typeof raw.a11y === 'object') { this.a11y = { large: !!raw.a11y.large, contrast: !!raw.a11y.contrast, calm: !!raw.a11y.calm }; }
                if (cfg.currencies.some((c) => c.code === raw.cur)) { this.curCode = raw.cur; }
            } catch (e) { /* nothing saved, or private mode */ }
            this.applyA11y();
        },
        savePrefs() {
            this.applyA11y();
            try { localStorage.setItem(cfg.prefsKey, JSON.stringify({ diet: this.diet, avoid: this.avoid, favs: this.favs, spice: this.spice, sort: this.sort, a11y: this.a11y, cur: this.curCode })); } catch (e) { /* ignore */ }
        },
        applyA11y() {
            const root = document.documentElement;
            root.toggleAttribute('data-menu-large', this.a11y.large);
            root.toggleAttribute('data-menu-contrast', this.a11y.contrast);
            root.toggleAttribute('data-menu-calm', this.a11y.calm);
        },

        // ---- menu assistant (AI) -----------------------------------------------------------
        async askAssistant() {
            const text = this.chat.input.trim();
            if (!text || this.chat.busy || !cfg.assistantUrl) { return; }
            const history = this.chat.msgs.map((m) => ({ role: m.role, text: m.text }));
            this.chat.msgs.push({ role: 'user', text }); this.chat.input = ''; this.chat.busy = true;
            try {
                const res = await fetch(cfg.assistantUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify({ message: text, history }) });
                const data = await res.json().catch(() => ({}));
                this.chat.msgs.push(res.ok ? { role: 'assistant', text: data.answer, products: data.products } : { role: 'assistant', text: data.message || cfg.t.generic_error });
            } catch (e) { this.chat.msgs.push({ role: 'assistant', text: cfg.t.generic_error }); }
            this.chat.busy = false;
            this.$nextTick(() => { const log = this.$refs.chatLog; if (log) { log.scrollTop = log.scrollHeight; } });
        },
        showDish(id) { const p = this.tree.flatMap((c) => c.products).find((x) => x.id === id); if (p) { this.chat.open = false; this.open(p); } },

        // ---- at the table ----------------------------------------------------------------
        async askService(kind) {
            try {
                const res = await fetch(this.ord.requestUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify({ kind }) });
                const data = await res.json().catch(() => ({}));
                this.flash(res.ok ? (data.message || 'OK') : (res.status === 429 ? cfg.t.too_many : cfg.t.generic_error));
            } catch (e) { this.flash(cfg.t.generic_error); }
        },
        async openTab() {
            try { const res = await fetch(this.ord.tabUrl, { headers: { Accept: 'application/json' } }); if (res.ok) { this.tab = await res.json(); } } catch (e) { this.flash(cfg.t.generic_error); }
        },
        // "Order again": ?reorder=<order token> puts that order's dishes back in the cart. Unavailable dishes are dropped, prices come from the server.
        async maybeReorder() {
            const token = new URLSearchParams(location.search).get('reorder');
            if (!token || !/^[a-z0-9]{24}$/.test(token)) { return; }
            try {
                const res = await fetch(cfg.orderBase + '/order/' + token + '/reorder', { headers: { Accept: 'application/json' } });
                if (!res.ok) { return; }
                const products = new Map(this.tree.flatMap((c) => c.products).map((p) => [p.id, p]));
                const lines = (await res.json()).lines.filter((l) => products.has(l.product_id) && products.get(l.product_id).available);
                const dropped = (await Promise.resolve(0), lines.length);
                this.cart = [];
                lines.forEach((l) => { const p = products.get(l.product_id); this.push({ product_id: l.product_id, variant_id: l.variant_id, combo: l.combo, options: l.options, qty: l.qty, note: l.note || '', name: p.name, labels: [], unit_cents: this.cents(p.price) }); });
                history.replaceState(null, '', location.pathname);
                if (this.cart.length) { this.cartOpen = true; this.flash(cfg.t.reorder_done); }
                void dropped;
            } catch (e) { /* the menu simply opens as usual */ }
        },

        // ---- install as an app, and the pop-up banner ------------------------------------
        initPwa() {
            if ('serviceWorker' in navigator && cfg.pwa) { navigator.serviceWorker.register(cfg.pwa.worker).catch(() => {}); }
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.installEvent = e; });
            window.addEventListener('appinstalled', () => { this.installEvent = null; });
        },
        async install() { if (!this.installEvent) { return; } this.installEvent.prompt(); await this.installEvent.userChoice.catch(() => {}); this.installEvent = null; },
        showPopup() {
            const b = cfg.banners.find((x) => x.popup);
            if (!b || cfg.kiosk) { return; }
            const key = 'qrmenu.popup.' + b.id + '.' + b.stamp;
            try { if (sessionStorage.getItem(key)) { return; } } catch (e) { /* show it */ }
            setTimeout(() => { this.popup = b; try { sessionStorage.setItem(key, '1'); } catch (e) { /* ignore */ } }, 1200);
        },
        get curObj() { return cfg.currencies.find((c) => c.code === this.curCode); },
        toggleIn(list, value) { const i = this[list].indexOf(value); i === -1 ? this[list].push(value) : this[list].splice(i, 1); },

        goTo(id) {
            this.active = id;
            const i = this.visible.findIndex((c) => c.id === id);
            if (i === -1) {
                const pool = this.menus.length && !this.hasFilters ? this.tree.filter((c) => c.menu_id === this.menuId) : this.tree;
                this.shown = Math.max(this.shown, pool.findIndex((c) => c.id === id) + 1);
                this.$nextTick(() => document.getElementById('cat-' + id)?.scrollIntoView({ block: 'start' }));
                return;
            }
            document.getElementById('cat-' + id)?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        },
        observe() {
            if (!('IntersectionObserver' in window)) { return; }
            this.io?.disconnect();
            const io = this.io = new IntersectionObserver((entries) => {
                entries.filter((e) => e.isIntersecting).forEach((e) => {
                    this.active = Number(e.target.dataset.cat);
                    document.querySelector('[data-tab="' + this.active + '"]')?.scrollIntoView({ inline: 'center', block: 'nearest' });
                });
            }, { rootMargin: '-96px 0px -70% 0px' });
            document.querySelectorAll('[data-cat]').forEach((el) => io.observe(el));
        },

        // ---- formatting ------------------------------------------------------------------
        // The cart and checkout are always in the restaurant's own currency; browsing can show an approximate price in another.
        money(cents) {
            const o = this.curObj;
            const c = o && !o.base && !this.cartOpen ? o : cfg.currency;
            if (c === o) { cents = cents * o.factor; }
            let [int, frac] = (cents / 100).toFixed(c.decimals).split('.');
            int = int.replace(/\B(?=(\d{3})+(?!\d))/g, c.thousands);
            const num = frac ? int + c.decimal + frac : int;
            if (!c.symbol) { return num; }
            return c.after ? num + ' ' + c.symbol : c.symbol + num;
        },
        cents(p) { return Math.round(p * 100); },
        // Dishes sold in sizes show "from" their lowest price.
        priceText(p) { const t = this.money(this.cents(p.price)); return (p.variants || []).length ? cfg.t.from_price + ' ' + t : t; },

        // ---- product sheet ---------------------------------------------------------------
        open(product, edit = null) {
            const selected = {};
            product.option_groups.forEach((g) => { selected[g.id] = g.options.filter((o) => o.default).map((o) => o.id); });
            if (edit) { Object.keys(selected).forEach((gid) => { selected[gid] = edit.options.filter((id) => product.option_groups.find((g) => g.id === Number(gid))?.options.some((o) => o.id === id)); }); }
            const variants = product.variants || [];
            const variant = edit?.variant_id ?? (variants.find((v) => v.available)?.id ?? null);
            const combo = {};
            (product.combo || []).forEach((slot) => { const first = slot.items.find((i) => i.available); combo[slot.id] = edit?.combo?.[slot.id] ?? (first ? first.id : null); });
            this.sheet = { product, selected, variant, combo, qty: edit?.qty ?? 1, note: edit?.note ?? '', editKey: edit?.key ?? null, showErrors: false, img: 0, video: false };
            this.$nextTick(() => this.$refs.sheetClose?.focus());
        },
        quickAdd(product, event = null) {
            if (!product.available) { return; }
            if (product.option_groups.length || (product.variants || []).length || (product.combo || []).length) { this.open(product); return; }
            this.push({ product_id: product.id, variant_id: null, combo: null, options: [], qty: 1, note: '', name: product.name, labels: [], unit_cents: this.cents(product.price) });
            this.flash(product.name);
            this.fly(event?.currentTarget);
        },
        pic(p) { return p.image || p.art; },
        // Every photo of a dish for its detail sheet: the main one first, then the gallery.
        photos(p) { return [this.big(p), ...(p.gallery || []).map((g) => g.full)].filter(Boolean); },
        // The detail sheet shows the full-size photo.
        big(p) { return p.image_full || p.image || p.art; },
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
            // Dishes the restaurant itself says go well with what is in the cart come first.
            const paired = new Set(this.tree.flatMap((c) => c.products).filter((p) => inCart.has(p.id)).flatMap((p) => p.pairs || []));
            const catOf = new Map(this.tree.flatMap((c) => c.products.map((p) => [p.id, c.id])));
            const cartCats = new Set(this.cart.map((l) => catOf.get(l.product_id)));
            return this.tree
                .flatMap((c) => c.products.map((p) => ({ ...p, cat: c.id })))
                .filter((p) => p.available && !inCart.has(p.id) && !(p.variants || []).length && !(p.combo || []).length && !p.option_groups.some((g) => g.required))
                .sort((a, b) => (paired.has(b.id) - paired.has(a.id)) || (cartCats.has(a.cat) - cartCats.has(b.cat)) || (b.featured - a.featured) || (a.price - b.price))
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
        get sheetBase() {
            const s = this.sheet;
            const v = (s.product.variants || []).find((x) => x.id === s.variant);
            return this.cents(v ? v.price : s.product.price);
        },
        get sheetUnit() {
            const s = this.sheet;
            const comboExtra = (s.product.combo || []).reduce((sum, slot) => sum + Math.round(((slot.items.find((i) => i.id === s.combo[slot.id]) || {}).delta || 0) * 100), 0);
            return comboExtra + s.product.option_groups.reduce((sum, g) => sum + g.options.filter((o) => s.selected[g.id].includes(o.id)).reduce((a, o) => a + Math.round(o.price_delta * 100), 0), this.sheetBase);
        },
        get variantMissing() { const s = this.sheet; return (s.product.variants || []).length > 0 && !(s.product.variants.find((v) => v.id === s.variant && v.available)); },
        get comboMissing() { const s = this.sheet; return (s.product.combo || []).some((slot) => !slot.items.find((i) => i.id === s.combo[slot.id] && i.available)); },
        get sheetValid() { return !this.variantMissing && !this.comboMissing && this.sheet.product.option_groups.every((g) => !this.groupMissing(g)); },
        step(delta) { this.sheet.qty = Math.min(50, Math.max(1, this.sheet.qty + delta)); },
        submitSheet() {
            const s = this.sheet;
            if (!this.sheetValid) { s.showErrors = true; return; }
            const options = s.product.option_groups.flatMap((g) => s.selected[g.id]);
            const variant = (s.product.variants || []).find((v) => v.id === s.variant);
            const comboLabels = (s.product.combo || []).map((slot) => (slot.items.find((i) => i.id === s.combo[slot.id]) || {}).name).filter(Boolean);
            const labels = [...(variant ? [variant.name] : []), ...comboLabels, ...s.product.option_groups.flatMap((g) => g.options.filter((o) => s.selected[g.id].includes(o.id)).map((o) => o.name))];
            const line = { product_id: s.product.id, variant_id: variant ? variant.id : null, combo: (s.product.combo || []).length ? { ...s.combo } : null, options, qty: s.qty, note: s.note.trim(), name: s.product.name, labels, unit_cents: Math.max(0, this.sheetUnit) };
            if (s.editKey) { this.cart = this.cart.filter((l) => l.key !== s.editKey); }
            this.push(line);
            this.sheet = null;
            this.flash(line.name);
            this.fly(null);
        },

        // ---- cart ------------------------------------------------------------------------
        key(l) { return [l.product_id, l.variant_id || 0, JSON.stringify(l.combo || {}), [...l.options].sort((a, b) => a - b).join(','), l.note].join('|'); },
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
            if (!this.cart.length) { this.quoted = null; this.totals = null; return; }
            this.quoting = true;
            this.timer = setTimeout(async () => {
                try {
                    const res = await fetch(cfg.quoteUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify({
                            lines: this.cart.map((l) => ({ product_id: l.product_id, variant_id: l.variant_id || null, combo: l.combo || undefined, options: l.options, qty: l.qty, note: l.note })),
                            type: this.stage === 'checkout' ? this.form.type : undefined,
                            promo_code: this.stage === 'checkout' && this.form.promo ? this.form.promo : undefined,
                            customer_email: this.form.email || undefined, customer_phone: this.form.phone || undefined,
                            delivery_zone: this.stage === 'checkout' && this.form.type === 'delivery' && this.form.zone ? this.form.zone : undefined,
                        }),
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
            // A signed-in guest's saved details win over what this browser remembers.
            this.form.name = cfg.account?.name || guest.name || '';
            this.form.phone = cfg.account?.phone || guest.phone || '';
            this.form.email = cfg.account?.email || guest.email || '';
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
        // Promo codes: the server decides whether a code is good and how much it takes off.
        applyPromo() { this.form.promo = this.promoInput.trim(); this.requote(0); },
        clearPromo() { this.form.promo = ''; this.promoInput = ''; this.requote(0); },
        get promo() { return this.quoted?.promo || null; },
        // Where the kitchen cannot just walk over: it needs a phone number to reach the guest. Room service reaches them in the room.
        get needsContact() { return ['takeaway', 'delivery', 'curbside'].includes(this.form.type); },
        get overMax() { return this.ord.maxItems > 0 && this.cart.reduce((n, l) => n + l.qty, 0) > this.ord.maxItems; },
        get notifyLabels() { return cfg.notifyLabels; },
        // Browser local time for the picker, as 'YYYY-MM-DDTHH:mm'.
        localStamp(d) { const p = (n) => String(n).padStart(2, '0'); return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes()); },
        get whenMin() { return this.ord.schedule ? this.localStamp(new Date(Date.now() + this.ord.schedule.lead * 60000)) : ''; },
        get whenMax() { return this.ord.schedule ? this.localStamp(new Date(Date.now() + this.ord.schedule.days * 86400000)) : ''; },
        validateForm() {
            const e = {};
            const f = this.form;
            if (f.type === 'dine_in' && !this.ord.table && !f.table_id) { e.table_id = cfg.t.table_required; }
            if ((this.needsContact || this.ord.requireName) && !f.name.trim()) { e.name = cfg.t.name_required; }
            if (this.needsContact && !/^[0-9+()\-\s.]{6,40}$/.test(f.phone.trim())) { e.phone = cfg.t.phone_required; }
            if (f.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) { e.email = cfg.t.email_invalid; }
            if (f.type === 'delivery' && f.address.trim().length < 5) { e.address = cfg.t.address_required; }
            if (f.type === 'delivery' && this.ord.zones.length && !f.zone) { e.zone = cfg.t.zone_required; }
            if (f.type === 'curbside' && !f.vehicle.trim()) { e.vehicle = cfg.t.vehicle_required; }
            if (f.type === 'room_service' && !f.room.trim()) { e.room = cfg.t.room_required; }
            if (this.ord.schedule && f.later && (!f.when || f.when < this.whenMin || f.when > this.whenMax)) { e.when = cfg.t.schedule_invalid; }
            if (this.overMax) { e.max = cfg.t.too_many_items; }
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
                        customer_name: f.name, customer_phone: f.phone, customer_email: f.email, marketing_opt_in: !!f.email.trim() && f.marketing, promo_code: f.promo || null, delivery_address: f.type === 'delivery' ? f.address : null,
                        delivery_zone: f.type === 'delivery' && f.zone ? f.zone : null, vehicle: f.type === 'curbside' ? f.vehicle : null, room: f.type === 'room_service' ? f.room : null,
                        scheduled_for: this.ord.schedule && f.later && f.when ? new Date(f.when).toISOString() : null, notify: f.notify || null,
                        note: f.note, payment_method: f.payment, idempotency_key: this.key, kiosk: cfg.kiosk ? 1 : undefined,
                        lines: this.cart.map((l) => ({ product_id: l.product_id, variant_id: l.variant_id || null, combo: l.combo || undefined, options: l.options, qty: l.qty, note: l.note })),
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 201) {
                    try { localStorage.setItem('qrmenu.guest', JSON.stringify({ name: f.name, phone: f.phone, email: f.email, address: f.address })); localStorage.removeItem(cfg.storageKey); } catch (e) { /* ignore */ }
                    this.cart = [];
                    if (cfg.kiosk) { this.kioskDone = { number: data.number }; this.cartOpen = false; setTimeout(() => this.kioskReset(), 12000); this.submitting = false; return; }
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

        // ---- kiosk: a screen guests order from by themselves -------------------------------
        startKiosk() {
            let idle = null;
            const arm = () => { clearTimeout(idle); idle = setTimeout(() => { if (this.cart.length || this.sheet || this.cartOpen) { this.kioskReset(); } }, 90000); };
            ['pointerdown', 'keydown', 'touchstart'].forEach((ev) => window.addEventListener(ev, arm, { passive: true }));
            arm();
            document.addEventListener('contextmenu', (e) => e.preventDefault());
        },
        // Next guest: forget the cart and the personal details, and start from the top.
        kioskReset() {
            try { localStorage.removeItem(cfg.storageKey); localStorage.removeItem('qrmenu.guest'); } catch (e) { /* ignore */ }
            window.location.href = window.location.pathname + '?kiosk=1';
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
