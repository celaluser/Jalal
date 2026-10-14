<x-layouts.app :title="__('marketing.widget_title')">
    <x-ui.page-header :title="__('marketing.widget_title')" :description="__('marketing.widget_sub')" :back="['url' => route('marketing.settings'), 'label' => __('marketing.loyalty_title')]" />
    <div class="max-w-3xl space-y-5">
        <x-ui.card :title="__('marketing.widget_button_title')">
            <p class="mb-3 text-sm text-muted">{{ __('marketing.widget_button_help') }}</p>
            <pre class="overflow-x-auto rounded-lg bg-surface-2 p-3 text-xs" dir="ltr"><code>&lt;script src="{{ $script }}" defer&gt;&lt;/script&gt;</code></pre>
        </x-ui.card>
        <x-ui.card :title="__('marketing.widget_iframe_title')">
            <p class="mb-3 text-sm text-muted">{{ __('marketing.widget_iframe_help') }}</p>
            <pre class="overflow-x-auto rounded-lg bg-surface-2 p-3 text-xs" dir="ltr"><code>&lt;iframe src="{{ $menu }}" style="border:0;width:100%;height:760px" title="{{ $restaurant->name }}"&gt;&lt;/iframe&gt;</code></pre>
        </x-ui.card>
    </div>
</x-layouts.app>
