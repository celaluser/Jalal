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

try { applyTheme(localStorage.getItem('theme') || 'system'); } catch (e) { applyTheme('system'); }
