<x-layouts.app :title="$survey->title">
    <x-ui.page-header :title="$survey->title" :description="trans_choice('surveys.responses', $survey->responses_count, ['count' => $survey->responses_count])">
        <x-ui.button :href="route('surveys.export', $survey->id)" variant="secondary" :block="false">{{ __('surveys.export') }}</x-ui.button>
    </x-ui.page-header>

    @if ($survey->responses_count === 0)
        <div class="card"><x-ui.empty icon="star" :title="__('surveys.no_responses')" :text="__('surveys.no_responses_text')" /></div>
    @else
        <div class="max-w-3xl space-y-4">
            @foreach ($summary as $q)
                <x-ui.card :title="$q['label']">
                    <p class="mb-3 text-xs text-muted"><bdi>{{ trans_choice('surveys.answered', $q['count'], ['count' => $q['count']]) }}</bdi></p>
                    @if ($q['type'] === 'rating')
                        <p class="text-3xl font-bold tnum">{{ $q['average'] !== null ? number_format($q['average'], 1) : '–' }} <span class="text-base font-normal text-muted">/ 5</span></p>
                        @include('surveys::staff.bars', ['values' => $q['distribution'], 'total' => max(1, $q['count'])])
                    @elseif ($q['type'] === 'nps')
                        <p class="text-3xl font-bold tnum">{{ $q['score'] !== null ? $q['score'] : '–' }} <span class="text-base font-normal text-muted">{{ __('surveys.nps_score') }}</span></p>
                        @include('surveys::staff.bars', ['values' => $q['distribution'], 'total' => max(1, $q['count'])])
                    @elseif ($q['type'] === 'choice')
                        @include('surveys::staff.bars', ['values' => $q['options'], 'total' => max(1, $q['count'])])
                    @else
                        <ul class="space-y-2 text-sm">
                            @forelse ($q['latest'] as $t)<li class="rounded-lg bg-surface-2 px-3 py-2">{{ $t }}</li>@empty<li class="text-muted">–</li>@endforelse
                        </ul>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
