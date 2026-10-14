<button type="button" x-data="themeToggle" x-on:click="toggle()" {{ $attributes->class('grid size-9 place-items-center rounded-lg text-muted transition hover:bg-surface-2 hover:text-fg') }} aria-label="{{ __('ui.toggle_theme') }}">
    <x-ui.icon name="moon" size="5" class="dark:hidden" />
    <x-ui.icon name="sun" size="5" class="hidden dark:block" />
</button>
