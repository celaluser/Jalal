<x-layouts.app :title="__('ui.dashboard')">
    <x-ui.card>
        <h1 class="text-xl font-semibold">{{ __('ui.welcome', ['name' => auth()->user()->name]) }}</h1>
    </x-ui.card>
</x-layouts.app>
