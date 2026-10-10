import Sortable from 'sortablejs';

// Alpine.js ships with Livewire 3 (@livewireScripts); do not bundle a second copy.

const applyTheme = (mode) => {
    const dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('themeToggle', () => ({
        mode: localStorage.getItem('theme') || 'system',
        set(mode) {
            this.mode = mode;
            try { localStorage.setItem('theme', mode); } catch (e) { /* storage unavailable */ }
            applyTheme(mode);
        },
        toggle() {
            this.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
        },
    }));
});

// Drag-and-drop ordering. Every child needs data-id; [data-handle] is the grab area. The up/down
// buttons ([data-move="-1|1"]) do the same thing for keyboard and screen-reader users.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('sortable', (url) => ({
        save() {
            const ids = [...this.$el.children].map((el) => Number(el.dataset.id)).filter(Boolean);
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ ids }),
            });
        },
        move(row, direction) {
            const sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) { return; }
            direction < 0 ? sibling.before(row) : sibling.after(row);
            this.save();
        },
        init() {
            Sortable.create(this.$el, { handle: '[data-handle]', animation: 160, ghostClass: 'opacity-40', onEnd: () => this.save() });
        },
    }));
});

// AI helpers of the menu editor. They only fill form fields: nothing is saved until the owner presses Save.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('aiTools', (cfg) => ({
        ai: cfg, busy: null, error: '', note: '', hints: '', tone: 'appetizing', panel: false, creditsText: cfg.creditsText,
        field(name, locale) { return document.getElementById(name + '-' + (locale || cfg.locale)); },
        set(el, value) { if (el) { el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); } },
        async call(task, url, body) {
            this.busy = task; this.error = ''; this.note = '';
            try {
                const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf }, body: JSON.stringify(body) });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.error = data.message || data.errors?.name?.[0] || 'Error'; return null; }
                if (data.credits) { this.creditsText = data.credits.remaining === null ? cfg.unlimitedText : cfg.leftText.replace(':count', data.credits.remaining); }
                return data;
            } catch (e) { this.error = cfg.failText; return null; } finally { this.busy = null; }
        },
        async describe() {
            const name = this.field('name')?.value.trim();
            if (!name) { this.error = cfg.needName; return; }
            const category = document.getElementById('category_id');
            const data = await this.call('describe', cfg.urls.describe, { name, hints: this.hints, tone: this.tone, category: category?.selectedOptions?.[0]?.text || '' });
            if (data) { this.set(this.field('description'), data.description); this.panel = false; }
        },
        async translate() {
            const texts = {};
            ['name', 'description'].forEach((f) => { const v = this.field(f)?.value.trim(); if (v) { texts[f] = v; } });
            if (!texts.name) { this.error = cfg.needName; return; }
            const targets = cfg.locales.filter((l) => l !== cfg.locale);
            const data = await this.call('translate', cfg.urls.translate, { texts, targets });
            if (!data) { return; }
            // Only empty fields are filled, so nothing the owner already wrote is overwritten.
            Object.entries(data.translations).forEach(([code, fields]) => Object.entries(fields).forEach(([f, v]) => { const el = this.field(f, code); if (el && !el.value.trim()) { this.set(el, v); } }));
        },
        async tags() {
            const name = this.field('name')?.value.trim();
            if (!name) { this.error = cfg.needName; return; }
            const data = await this.call('tags', cfg.urls.tags, { name, description: this.field('description')?.value || '', hints: this.hints });
            if (!data) { return; }
            const ticked = [];
            [['allergens[]', data.allergens], ['dietary[]', data.dietary]].forEach(([n, values]) => values.forEach((v) => {
                const box = document.querySelector('input[name="' + n + '"][value="' + v + '"]');
                if (box) { box.checked = true; ticked.push(box.closest('label')?.innerText.trim() || v); }
            }));
            this.note = ticked.length ? cfg.tickedText.replace(':list', ticked.join(', ')) : cfg.noneText;
        },
    }));
});

try { applyTheme(localStorage.getItem('theme') || 'system'); } catch (e) { applyTheme('system'); }
