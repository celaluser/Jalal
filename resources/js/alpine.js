// Alpine.js, bundled once. Pages used to load the whole of Livewire (about 340 KB more) just to get it.
// Components are registered on 'alpine:init' by the other bundles (storefront, orders, map, app); all of those run before
// DOMContentLoaded, where Alpine starts, so none is missed.
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.plugin(focus);
    document.addEventListener('DOMContentLoaded', () => Alpine.start());
}
