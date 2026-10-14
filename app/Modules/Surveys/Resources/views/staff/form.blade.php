@php
    $editing = $survey->exists;
    $initial = old('questions') ? collect(old('questions'))->map(fn ($q) => ['id' => $q['id'] ?? '', 'type' => $q['type'] ?? 'text', 'label' => $q['label'] ?? '', 'required' => ! empty($q['required']), 'options' => $q['options'] ?? ''])->values() : collect($survey->questions)->map(fn ($q) => ['id' => $q['id'], 'type' => $q['type'], 'label' => $q['label'], 'required' => (bool) $q['required'], 'options' => implode("\n", $q['options'] ?? [])])->values();
@endphp
<x-layouts.app :title="$editing ? __('surveys.edit') : __('surveys.new')">
    <x-ui.page-header :title="$editing ? __('surveys.edit') : __('surveys.new')" :description="__('surveys.form_sub')" />

    <form method="POST" action="{{ $editing ? route('surveys.update', $survey->id) : route('surveys.store') }}" class="max-w-3xl space-y-5"
          x-data="{ qs: @js($initial), max: {{ \App\Modules\Surveys\Models\Survey::MAX_QUESTIONS }}, add() { if (this.qs.length < this.max) this.qs.push({ id: '', type: 'rating', label: '', required: false, options: '' }) }, move(i, d) { const j = i + d; if (j < 0 || j >= this.qs.length) return; [this.qs[i], this.qs[j]] = [this.qs[j], this.qs[i]] } }">
        @csrf @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-4">
                <x-ui.input name="title" :label="__('surveys.f_title')" :value="old('title', $survey->title)" required maxlength="120" />
                <x-ui.input name="intro" :label="__('surveys.f_intro')" :value="old('intro', $survey->intro)" maxlength="500" />
                <x-ui.input name="thank_you" :label="__('surveys.f_thanks')" :value="old('thank_you', $survey->thank_you)" maxlength="300" />
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <x-ui.checkbox name="is_active" :label="__('surveys.f_active')" :checked="$survey->is_active" />
                    <x-ui.checkbox name="after_order" :label="__('surveys.f_after_order')" :checked="$survey->after_order" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('surveys.f_questions')">
            @error('questions')<p class="mb-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            <ol class="space-y-3">
                <template x-for="(q, i) in qs" :key="i">
                    <li class="rounded-xl border border-line p-3">
                        <input type="hidden" :name="`questions[${i}][id]`" :value="q.id">
                        <div class="grid gap-3 sm:grid-cols-6">
                            <div class="sm:col-span-4">
                                <label class="mb-1 block text-xs font-medium text-muted">{{ __('surveys.f_question') }} <span x-text="i + 1"></span></label>
                                <input type="text" class="input" :name="`questions[${i}][label]`" x-model="q.label" maxlength="160" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-muted">{{ __('surveys.f_type') }}</label>
                                <select class="input" :name="`questions[${i}][type]`" x-model="q.type">
                                    @foreach (\App\Modules\Surveys\Models\Survey::TYPES as $t)<option value="{{ $t }}">{{ __('surveys.type_'.$t) }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-3" x-show="q.type === 'choice'" x-cloak>
                            <label class="mb-1 block text-xs font-medium text-muted">{{ __('surveys.f_options') }}</label>
                            <textarea class="input" rows="3" :name="`questions[${i}][options]`" x-model="q.options" maxlength="800"></textarea>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <label class="flex items-center gap-2 text-sm"><input type="hidden" :name="`questions[${i}][required]`" value="0"><input type="checkbox" class="check" :name="`questions[${i}][required]`" value="1" x-model="q.required"> {{ __('surveys.f_required') }}</label>
                            <div class="flex items-center gap-1 text-sm">
                                <button type="button" class="rounded px-2 py-1 hover:bg-surface-2" @click="move(i, -1)" :disabled="i === 0" aria-label="{{ __('surveys.up') }}">↑</button>
                                <button type="button" class="rounded px-2 py-1 hover:bg-surface-2" @click="move(i, 1)" :disabled="i === qs.length - 1" aria-label="{{ __('surveys.down') }}">↓</button>
                                <button type="button" class="rounded px-2 py-1 text-red-600 hover:bg-surface-2" @click="qs.splice(i, 1)" x-show="qs.length > 1">{{ __('admin.delete') }}</button>
                            </div>
                        </div>
                    </li>
                </template>
            </ol>
            <button type="button" class="btn-secondary mt-3" @click="add()" :disabled="qs.length >= max">{{ __('surveys.add_question') }}</button>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <x-ui.button :href="route('surveys.index')" variant="secondary" :block="false">{{ __('admin.cancel') }}</x-ui.button>
            <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
