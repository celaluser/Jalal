<x-layouts.admin :title="__('addons.title')">
    <x-ui.page-header :title="__('addons.title')" :description="__('addons.sub')" />
    @error('addon')<x-ui.alert type="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror
    <div class="grid gap-5 lg:grid-cols-5">
        @if ($uploads)
            <x-ui.card :title="__('addons.install')" :description="__('addons.install_help')" class="lg:col-span-2">
                <form method="POST" action="{{ route('admin.addons.upload') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="file" name="package" accept=".zip" required class="field">
                    @error('package')<p class="flex items-center gap-1 text-sm text-red-600" role="alert"><x-ui.icon name="alert" size="4" />{{ $message }}</p>@enderror
                    <x-ui.alert type="warning">{{ __('addons.trust_warning') }}</x-ui.alert>
                    <x-ui.button icon="check">{{ __('addons.install_button') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endif
        <x-ui.card :title="__('addons.installed_title')" :pad="false" class="{{ $uploads ? 'lg:col-span-3' : 'lg:col-span-5' }}">
            <ul class="divide-y divide-line text-sm">
                @forelse ($addons as $slug => $a)
                    @php($on = $manager->enabled($slug))
                    <li class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $a['name'] }} <span class="tnum font-normal text-muted">{{ $a['version'] }}</span></p>
                            <p class="text-muted">{{ $a['description'] ?? '' }}@if (! empty($a['author'])) · {{ $a['author'] }}@endif</p>
                            @if ($on && $manager->error($slug))<p class="mt-1 text-red-600">{{ $manager->error($slug) }}</p>@endif
                            @unless ($manager->compatible($a))<p class="mt-1 text-red-600">{{ __('addons.needs_core', ['version' => $a['min_core']]) }}</p>@endunless
                        </div>
                        <x-ui.badge :tone="$on ? 'success' : 'neutral'" dot>{{ $on ? __('addons.on') : __('addons.off') }}</x-ui.badge>
                        <form method="POST" action="{{ route('admin.addons.toggle', $slug) }}">@csrf<button class="btn btn-secondary btn-sm">{{ $on ? __('addons.turn_off') : __('addons.turn_on') }}</button></form>
                        @if ($uploads)<form method="POST" action="{{ route('admin.addons.destroy', $slug) }}" onsubmit="return confirm('{{ __('addons.remove_confirm') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="{{ __('admin.delete') }}"><x-ui.icon name="trash" size="4" /></button></form>@endif
                    </li>
                @empty
                    <li><x-ui.empty icon="layers" :title="__('addons.empty')" :text="__('addons.empty_text')" /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</x-layouts.admin>
