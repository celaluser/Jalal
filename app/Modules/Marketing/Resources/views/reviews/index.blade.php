<x-layouts.app :title="__('marketing.reviews_title')">
    <x-ui.page-header :title="__('marketing.reviews_title')" :description="__('marketing.reviews_sub')" />

    @if ($lowOpen > 0)
        <x-ui.alert type="warning" class="mb-5">{{ trans_choice('marketing.low_alert', $lowOpen, ['count' => $lowOpen]) }} <a class="font-semibold underline" href="{{ route('reviews.index', ['filter' => 'low']) }}">{{ __('marketing.filter_low') }}</a></x-ui.alert>
    @endif

    @if ($summary['count'] === 0 && ! $filter)
        <div class="card"><x-ui.empty icon="star" :title="__('marketing.reviews_empty')" :text="__('marketing.reviews_empty_text')" /></div>
    @else
        <div class="grid items-start gap-5 lg:grid-cols-[18rem_1fr]">
            <div class="card card-pad lg:sticky lg:top-20">
                <p class="text-sm text-muted">{{ __('marketing.average') }}</p>
                <p class="display tnum mt-1 text-5xl font-bold leading-none">{{ number_format($summary['average'], 1) }}</p>
                <div class="mt-2"><x-marketing::stars :rating="$summary['average']" size="5" /></div>
                <p class="mt-1 text-sm text-muted">{{ trans_choice('marketing.review_count', $summary['count'], ['count' => $summary['count']]) }}</p>
                @if ($nps['count'] > 0)
                    <div class="mt-5 border-t border-line pt-4">
                        <p class="text-sm text-muted">{{ __('marketing.nps_score') }}</p>
                        <p class="display tnum mt-1 text-3xl font-bold leading-none">{{ $nps['score'] > 0 ? '+' : '' }}{{ $nps['score'] }}</p>
                        <p class="mt-1 text-xs text-muted">{{ __('marketing.nps_breakdown', ['p' => $nps['promoters'], 'n' => $nps['passives'], 'd' => $nps['detractors']]) }}</p>
                    </div>
                @endif
                <div class="mt-5 space-y-2">
                    @foreach ($summary['distribution'] as $star => $n)
                        <div class="flex items-center gap-2 text-sm"><span class="tnum w-3 text-muted">{{ $star }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-brand-500" style="width: {{ $summary['count'] ? round($n / $summary['count'] * 100) : 0 }}%"></div></div>
                            <span class="tnum w-6 text-end text-muted">{{ $n }}</span></div>
                    @endforeach
                </div>
            </div>

            <div class="min-w-0 space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach ([null => 'filter_all', 'low' => 'filter_low', 'unanswered' => 'filter_unanswered'] as $key => $label)
                        <a href="{{ route('reviews.index', $key ? ['filter' => $key] : []) }}" class="chip {{ ($filter ?: null) === $key ? 'chip-active' : '' }}">{{ __('marketing.'.$label) }}</a>
                    @endforeach
                </div>

                <ul class="space-y-3">
                    @forelse ($reviews as $r)
                        <li class="card p-5 {{ $r->isLow() && ! $r->replied_at ? 'ring-1 ring-red-300 dark:ring-red-800' : '' }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-3"><x-marketing::stars :rating="$r->rating" />
                                    <span class="font-semibold">{{ $r->author ?: __('marketing.anonymous') }}</span>
                                    @if ($r->isLow() && ! $r->replied_at)<x-ui.badge tone="danger" dot>{{ __('marketing.low_flag') }}</x-ui.badge>@endif
                                    @unless ($r->is_public)<x-ui.badge>{{ __('marketing.hidden') }}</x-ui.badge>@endunless</div>
                                <p class="text-sm text-muted"><a class="link" href="{{ route('orders.show', $r->order_id) }}">{{ __('marketing.order_ref', ['number' => '#'.$r->order?->number]) }}</a> · {{ $r->created_at->diffForHumans() }}</p>
                            </div>
                            @if ($r->comment)<p class="mt-3 whitespace-pre-line">{{ $r->comment }}</p>@endif

                            @if ($canManage)
                                <form method="POST" action="{{ route('reviews.reply', $r->id) }}" class="mt-4 space-y-2" x-data="{ open: {{ $r->reply ? 'true' : 'false' }}, busy: false, aiError: '', async draft() { this.busy = true; this.aiError = ''; try { const res = await fetch(@js(route('ai.review-reply', $r->id)), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } }); const d = await res.json().catch(() => ({})); if (res.ok) { this.$refs.reply.value = d.reply; } else { this.aiError = d.message || ''; } } catch (e) { this.aiError = @js(__('ai.assistant_error')); } this.busy = false; } }">
                                    @csrf
                                    <button type="button" class="text-sm font-medium text-accent-700 hover:underline dark:text-accent-300" x-show="!open" x-on:click="open = true">{{ __('marketing.reply') }}</button>
                                    <div x-show="open" x-cloak class="space-y-2">
                                        <label class="text-sm font-medium" for="reply-{{ $r->id }}">{{ __('marketing.reply') }}</label>
                                        <textarea id="reply-{{ $r->id }}" name="reply" rows="2" maxlength="1000" class="field" x-ref="reply">{{ $r->reply }}</textarea>
                                        @if ($aiEnabled)<div class="flex flex-wrap items-center gap-2"><button type="button" class="btn btn-secondary btn-sm" x-on:click="draft()" :disabled="busy"><x-ui.icon name="sparkles" size="4" />{{ __('ai.draft_reply') }}</button><span class="text-xs text-muted">{{ __('ai.draft_note') }}</span></div><p class="text-sm text-red-600" x-show="aiError" x-text="aiError" role="alert"></p>@endif
                                        <p class="text-xs text-muted">{{ __('marketing.reply_help') }}</p>
                                        <x-ui.button :block="false" class="btn-sm">{{ __('marketing.reply_save') }}</x-ui.button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('reviews.toggle', $r->id) }}" class="mt-2">@csrf<button class="text-xs text-muted hover:underline">{{ $r->is_public ? __('marketing.hide') : __('marketing.show') }}</button></form>
                            @elseif ($r->reply)
                                <p class="mt-3 rounded-lg bg-surface-2 p-3 text-sm">{{ $r->reply }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="card"><x-ui.empty icon="star" :title="__('marketing.reviews_empty')" /></li>
                    @endforelse
                </ul>
                {{ $reviews->links() }}
            </div>
        </div>
    @endif
</x-layouts.app>
