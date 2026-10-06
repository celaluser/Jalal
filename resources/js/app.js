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

try { applyTheme(localStorage.getItem('theme') || 'system'); } catch (e) { applyTheme('system'); }
