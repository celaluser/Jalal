<x-layouts.admin :title="__('admin.nav.landing')">
    <x-ui.page-header :title="__('admin.nav.landing')" :description="$translated ? __('admin.cms.landing_saved_for_locale') : __('admin.cms.landing_not_translated')">
        <x-slot:actions><a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-ui.icon name="external" size="4" />{{ __('ui.website') }}</a></x-slot:actions>
    </x-ui.page-header>
    <div class="max-w-3xl space-y-5">
        <nav class="flex flex-wrap gap-1.5 text-sm" aria-label="{{ __('admin.email_templates.languages') }}">
            @foreach ($locales as $code => $name)
                <a href="{{ route('admin.landing.edit', ['locale' => $code]) }}" @class(['rounded-lg px-3 py-1.5 font-medium transition', 'bg-ink-950 text-white dark:bg-white dark:text-ink-950' => $code === $locale, 'bg-surface-2 text-muted hover:text-fg' => $code !== $locale])>{{ $name }}</a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('admin.landing.update') }}" class="space-y-5">
            @csrf @method('PUT')
            <input type="hidden" name="locale" value="{{ $locale }}">

            <x-ui.card :title="__('admin.cms.hero')">
                <div class="space-y-4">
                    <x-ui.input name="hero[title]" :label="__('admin.cms.title')" :value="$c['hero']['title']" required />
                    <x-ui.input name="hero[subtitle]" :label="__('admin.cms.subtitle')" :value="$c['hero']['subtitle']" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="hero[cta_label]" :label="__('admin.cms.cta_label')" :value="$c['hero']['cta_label']" required />
                        <x-ui.input name="hero[secondary_label]" :label="__('admin.cms.secondary_label')" :value="$c['hero']['secondary_label'] ?? ''" />
                    </div>
                </div>
            </x-ui.card>

            @foreach ([
                'features' => [['title' => 'admin.cms.title', 'text' => 'admin.cms.text'], 'admin.cms.features', 'admin.cms.add_feature'],
                'faq' => [['question' => 'admin.cms.question', 'answer' => 'admin.cms.answer'], 'admin.cms.faq', 'admin.cms.add_faq'],
                'testimonials' => [['name' => 'admin.cms.person', 'role' => 'admin.cms.role', 'quote' => 'admin.cms.quote'], 'admin.cms.testimonials', 'admin.cms.add_testimonial'],
            ] as $section => [$fields, $heading, $addLabel])
                <x-ui.card :title="__($heading)">
                    <div x-data="{ rows: @js(array_values($c[$section] ?? [])) }" class="space-y-3">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="relative grid gap-2 rounded-xl border border-line bg-surface-2/40 p-3.5 pe-12">
                                @foreach ($fields as $field => $label)
                                    <input type="text" x-model="row.{{ $field }}" :name="`{{ $section }}[${i}][{{ $field }}]`" placeholder="{{ __($label) }}" aria-label="{{ __($label) }}" class="field">
                                @endforeach
                                <button type="button" x-on:click="rows.splice(i, 1)" class="absolute end-2 top-2 grid size-8 place-items-center rounded-lg text-muted hover:bg-surface hover:text-red-600" aria-label="{{ __('admin.cms.remove_row') }}"><x-ui.icon name="trash" size="4" /></button>
                            </div>
                        </template>
                        <button type="button" x-on:click="rows.push({ @foreach (array_keys($fields) as $field){{ $field }}: '', @endforeach })" class="btn btn-secondary btn-sm"><x-ui.icon name="plus" size="4" />{{ __($addLabel) }}</button>
                    </div>
                </x-ui.card>
            @endforeach

            <x-ui.card :title="__('admin.cms.pricing')" :description="__('admin.cms.pricing_help')">
                <div class="space-y-4">
                    <x-ui.input name="pricing[title]" :label="__('admin.cms.title')" :value="$c['pricing']['title']" required />
                    <x-ui.input name="pricing[subtitle]" :label="__('admin.cms.subtitle')" :value="$c['pricing']['subtitle'] ?? ''" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.cms.contact')">
                <div class="space-y-4">
                    <x-ui.input name="contact[title]" :label="__('admin.cms.title')" :value="$c['contact']['title'] ?? ''" />
                    <x-ui.input name="contact[text]" :label="__('admin.cms.text')" :value="$c['contact']['text'] ?? ''" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="contact[email]" type="email" :label="__('auth.email')" :value="$c['contact']['email'] ?? ''" />
                        <x-ui.input name="contact[phone]" :label="__('admin.cms.phone')" :value="$c['contact']['phone'] ?? ''" />
                    </div>
                    <x-ui.input name="contact[address]" :label="__('admin.cms.address')" :value="$c['contact']['address'] ?? ''" />
                </div>
            </x-ui.card>

            <x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button>
        </form>
    </div>
</x-layouts.admin>
