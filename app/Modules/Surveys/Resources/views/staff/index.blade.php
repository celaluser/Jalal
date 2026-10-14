<x-layouts.app :title="__('surveys.title')">
    <x-ui.page-header :title="__('surveys.title')" :description="__('surveys.sub')">
        <x-ui.button :href="route('surveys.create')" :block="false" icon="plus">{{ __('surveys.new') }}</x-ui.button>
    </x-ui.page-header>

    @if ($surveys->isEmpty())
        <div class="card"><x-ui.empty icon="star" :title="__('surveys.empty')" :text="__('surveys.empty_text')" /></div>
    @else
        <ul class="grid gap-3">
            @foreach ($surveys as $s)
                @php($url = $restaurant->publicUrl('survey/'.$s->slug))
                <li class="card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $s->title }}
                                <x-ui.badge :tone="$s->is_active ? 'success' : 'neutral'">{{ $s->is_active ? __('surveys.active') : __('surveys.off') }}</x-ui.badge>
                                @if ($s->after_order)<x-ui.badge tone="info">{{ __('surveys.after_order_badge') }}</x-ui.badge>@endif
                            </p>
                            <p class="mt-0.5 text-xs text-muted"><bdi>{{ trans_choice('surveys.responses', $s->responses_count, ['count' => $s->responses_count]) }}</bdi> · <bdi>{{ trans_choice('surveys.questions_count', count($s->questions), ['count' => count($s->questions)]) }}</bdi></p>
                            <p class="mt-1 break-all text-xs" dir="ltr"><a class="link" href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button :href="route('surveys.results', $s->id)" variant="secondary" size="sm" :block="false">{{ __('surveys.results') }}</x-ui.button>
                            <x-ui.button :href="route('surveys.edit', $s->id)" variant="secondary" size="sm" :block="false">{{ __('admin.edit') }}</x-ui.button>
                            <form method="POST" action="{{ route('surveys.destroy', $s->id) }}" onsubmit="return confirm('{{ __('surveys.delete_confirm') }}')">@csrf @method('DELETE')<button class="px-2 text-sm text-red-600">{{ __('admin.delete') }}</button></form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
