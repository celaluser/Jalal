<x-layouts.admin :title="__('admin.nav.email_templates')">
    <p class="mb-4 max-w-3xl text-sm text-gray-500">{{ __('admin.email_templates.help') }}</p>
    <x-ui.card class="max-w-3xl">
        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($templates as $key => $template)
                <li class="flex items-center justify-between py-3">
                    <div>
                        <a class="font-medium text-brand-600 hover:underline" href="{{ route('admin.email-templates.edit', $key) }}">{{ $template['label'] }}</a>
                        <p class="text-xs text-gray-500">{{ $key }}@if (! $template['required']) · {{ __('admin.email_templates.optional') }}@endif</p>
                    </div>
                    <span class="text-xs text-gray-500">{{ isset($customised[$key]) ? __('admin.email_templates.customised', ['locales' => strtoupper(implode(', ', $customised[$key]))]) : __('admin.email_templates.default') }}</span>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</x-layouts.admin>
