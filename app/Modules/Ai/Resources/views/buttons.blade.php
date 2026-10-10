{{--
    AI helpers for a menu form. Needs $ai (AiPanel::for) and the form wrapped in x-data="aiTools(...)".
    $mode: description | translate | tags
--}}
@if ($ai['enabled'])
    @if ($mode === 'description')
        <div class="mt-2">
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="panel = !panel" :aria-expanded="panel"><x-ui.icon name="sparkles" size="4" />{{ __('ai.write') }}</button>
            <div x-show="panel" x-cloak class="card mt-2 space-y-3 p-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium text-muted" for="ai-hints">{{ __('ai.hints') }}</label><input id="ai-hints" x-model="hints" maxlength="500" class="field"></div>
                    <div><label class="mb-1 block text-xs font-medium text-muted" for="ai-tone">{{ __('ai.tone') }}</label>
                        <select id="ai-tone" x-model="tone" class="field">@foreach (\App\Modules\Ai\Services\AiAssistant::TONES as $t)<option value="{{ $t }}">{{ __('ai.tone_'.$t) }}</option>@endforeach</select></div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn btn-primary btn-sm" x-on:click="describe()" :disabled="busy"><span x-text="busy === 'describe' ? @js(__('ai.writing')) : @js(__('ai.write'))"></span></button>
                    <span class="text-xs text-muted">{{ __('ai.credits_cost', ['count' => $ai['costs']['description']]) }} · <span x-text="creditsText"></span></span>
                </div>
                <p class="text-xs text-muted">{{ __('ai.review_note') }}</p>
            </div>
            <p class="mt-2 text-sm text-red-600 dark:text-red-400" x-show="error" x-text="error" role="alert"></p>
        </div>
    @elseif ($mode === 'translate' && count($ai['locales']) > 1)
        <div class="mt-3 flex flex-wrap items-center gap-3 rounded-xl bg-surface-2/60 px-3 py-2.5">
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="translate()" :disabled="busy"><x-ui.icon name="languages" size="4" /><span x-text="busy === 'translate' ? @js(__('ai.translating')) : @js(__('ai.translate'))"></span></button>
            <span class="text-xs text-muted">{{ __('ai.translate_hint') }} {{ __('ai.credits_cost', ['count' => $ai['costs']['translation']]) }}</span>
        </div>
        <p class="mt-2 text-sm text-red-600 dark:text-red-400" x-show="error" x-text="error" role="alert"></p>
    @elseif ($mode === 'tags')
        <div class="mb-3 flex flex-wrap items-center gap-3">
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="tags()" :disabled="busy"><x-ui.icon name="sparkles" size="4" /><span x-text="busy === 'tags' ? @js(__('ai.suggesting')) : @js(__('ai.suggest_tags'))"></span></button>
            <span class="text-xs text-muted">{{ __('ai.credits_cost', ['count' => $ai['costs']['allergens']]) }}</span>
        </div>
        <p class="mb-3 rounded-lg bg-brand-100 px-3 py-2 text-sm text-brand-900 dark:bg-brand-900/40 dark:text-brand-100" x-show="note" x-text="note" role="status"></p>
        <p class="mb-3 text-sm text-red-600 dark:text-red-400" x-show="error" x-text="error" role="alert"></p>
        <p class="mb-3 text-xs text-muted" x-show="note">{{ __('ai.tags_notice') }}</p>
    @endif
@endif
