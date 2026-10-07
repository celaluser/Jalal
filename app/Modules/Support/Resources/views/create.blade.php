<x-layouts.app :title="__('support.new_ticket')">
    <form method="POST" action="{{ route('support.store') }}" class="mx-auto max-w-2xl">
        @csrf
        <x-ui.card class="space-y-4">
            <h1 class="text-xl font-semibold">{{ __('support.new_ticket') }}</h1>
            <x-ui.input name="subject" :label="__('support.subject')" required autofocus />
            <x-ui.select name="priority" :label="__('support.priority')" value="normal" :options="['low' => __('support.priority_low'), 'normal' => __('support.priority_normal'), 'high' => __('support.priority_high')]" />
            <div>
                <label for="message" class="mb-1 block text-sm font-medium">{{ __('support.message') }}</label>
                <textarea id="message" name="message" rows="8" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>
            <x-ui.button class="!w-auto">{{ __('support.send') }}</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
