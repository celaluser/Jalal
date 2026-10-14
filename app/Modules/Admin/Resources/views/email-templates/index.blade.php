<x-layouts.admin :title="__('admin.nav.email_templates')">
    <x-ui.page-header :title="__('admin.nav.email_templates')" :description="__('admin.email_templates.help')" />
    <x-ui.card :pad="false" class="max-w-3xl">
        <ul class="divide-y divide-line">
            @foreach ($templates as $key => $template)
                <li><a href="{{ route('admin.email-templates.edit', $key) }}" class="flex items-center gap-4 px-5 py-4 transition hover:bg-surface-2/60">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-2"><x-ui.icon name="mail" size="5" /></span>
                    <span class="min-w-0 flex-1"><span class="block font-medium">{{ $template['label'] }}</span><span class="block text-xs text-muted">{{ $key }}@if (! $template['required']) · {{ __('admin.email_templates.optional') }}@endif</span></span>
                    <x-ui.badge :tone="isset($customised[$key]) ? 'success' : 'neutral'">{{ isset($customised[$key]) ? __('admin.email_templates.customised', ['locales' => strtoupper(implode(', ', $customised[$key]))]) : __('admin.email_templates.default') }}</x-ui.badge>
                    <x-ui.icon name="chevron-right" size="4" class="text-muted rtl:rotate-180" />
                </a></li>
            @endforeach
        </ul>
    </x-ui.card>
</x-layouts.admin>
