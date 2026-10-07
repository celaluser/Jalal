<x-layouts.app :title="__('support.new_ticket')">
    <x-ui.page-header :title="__('support.new_ticket')" :back="['url' => route('support.index'), 'label' => __('support.title')]" />
    <form method="POST" action="{{ route('support.store') }}" class="max-w-2xl">
        @csrf
        <x-ui.card class="space-y-5">
            <x-ui.input name="subject" :label="__('support.subject')" required autofocus />
            <x-ui.select name="priority" :label="__('support.priority')" value="normal" :options="['low' => __('support.priority_low'), 'normal' => __('support.priority_normal'), 'high' => __('support.priority_high')]" />
            <div>
                <label for="message" class="mb-1.5 block text-sm font-medium">{{ __('support.message') }}</label>
                <textarea id="message" name="message" rows="8" required class="field">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>
            <x-ui.button :block="false" size="lg" icon="arrow-right">{{ __('support.send') }}</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
