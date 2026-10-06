<x-layouts.admin :title="__('admin.nav.landing')">
    <div class="mx-auto max-w-3xl space-y-4">
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('admin.email_templates.languages') }}">
            @foreach ($locales as $code => $name)
                <a href="{{ route('admin.landing.edit', ['locale' => $code]) }}" @class(['rounded-full px-3 py-1', 'bg-brand-600 text-white' => $code === $locale, 'bg-gray-200 dark:bg-gray-800' => $code !== $locale])>{{ $name }}</a>
            @endforeach
        </nav>
        <p class="text-sm text-gray-500">{{ $translated ? __('admin.cms.landing_saved_for_locale') : __('admin.cms.landing_not_translated') }}</p>

        <form method="POST" action="{{ route('admin.landing.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <input type="hidden" name="locale" value="{{ $locale }}">

            <x-ui.card class="space-y-4">
                <h2 class="font-semibold">{{ __('admin.cms.hero') }}</h2>
                <x-ui.input name="hero[title]" :label="__('admin.cms.title')" :value="$c['hero']['title']" required />
                <x-ui.input name="hero[subtitle]" :label="__('admin.cms.subtitle')" :value="$c['hero']['subtitle']" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="hero[cta_label]" :label="__('admin.cms.cta_label')" :value="$c['hero']['cta_label']" required />
                    <x-ui.input name="hero[secondary_label]" :label="__('admin.cms.secondary_label')" :value="$c['hero']['secondary_label'] ?? ''" />
                </div>
            </x-ui.card>

            @foreach ([
                'features' => ['title', ['title' => 'admin.cms.title', 'text' => 'admin.cms.text'], 'admin.cms.features', 'admin.cms.add_feature'],
                'faq' => ['question', ['question' => 'admin.cms.question', 'answer' => 'admin.cms.answer'], 'admin.cms.faq', 'admin.cms.add_faq'],
                'testimonials' => ['name', ['name' => 'admin.cms.person', 'role' => 'admin.cms.role', 'quote' => 'admin.cms.quote'], 'admin.cms.testimonials', 'admin.cms.add_testimonial'],
            ] as $section => [$first, $fields, $heading, $addLabel])
                <x-ui.card>
                    <h2 class="mb-3 font-semibold">{{ __($heading) }}</h2>
                    <div x-data="{ rows: @js(array_values($c[$section] ?? [])) }" class="space-y-3">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="grid gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                @foreach ($fields as $field => $label)
                                    <input type="text" x-model="row.{{ $field }}" :name="`{{ $section }}[${i}][{{ $field }}]`" placeholder="{{ __($label) }}" aria-label="{{ __($label) }}"
                                           class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                                @endforeach
                                <button type="button" x-on:click="rows.splice(i, 1)" class="w-fit text-xs text-red-600 hover:underline">{{ __('admin.cms.remove_row') }}</button>
                            </div>
                        </template>
                        <button type="button" x-on:click="rows.push({ @foreach (array_keys($fields) as $field){{ $field }}: '', @endforeach })" class="text-sm font-medium text-brand-600 hover:underline">+ {{ __($addLabel) }}</button>
                    </div>
                </x-ui.card>
            @endforeach

            <x-ui.card class="space-y-4">
                <h2 class="font-semibold">{{ __('admin.cms.pricing') }}</h2>
                <p class="text-xs text-gray-500">{{ __('admin.cms.pricing_help') }}</p>
                <x-ui.input name="pricing[title]" :label="__('admin.cms.title')" :value="$c['pricing']['title']" required />
                <x-ui.input name="pricing[subtitle]" :label="__('admin.cms.subtitle')" :value="$c['pricing']['subtitle'] ?? ''" />
            </x-ui.card>

            <x-ui.card class="space-y-4">
                <h2 class="font-semibold">{{ __('admin.cms.contact') }}</h2>
                <x-ui.input name="contact[title]" :label="__('admin.cms.title')" :value="$c['contact']['title'] ?? ''" />
                <x-ui.input name="contact[text]" :label="__('admin.cms.text')" :value="$c['contact']['text'] ?? ''" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="contact[email]" type="email" :label="__('auth.email')" :value="$c['contact']['email'] ?? ''" />
                    <x-ui.input name="contact[phone]" :label="__('admin.cms.phone')" :value="$c['contact']['phone'] ?? ''" />
                </div>
                <x-ui.input name="contact[address]" :label="__('admin.cms.address')" :value="$c['contact']['address'] ?? ''" />
            </x-ui.card>

            <x-ui.button class="!w-auto">{{ __('admin.save') }}</x-ui.button>
        </form>
    </div>
</x-layouts.admin>
