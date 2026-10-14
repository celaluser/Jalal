<x-layouts.app :title="__('chat.title')">
    <x-ui.page-header :title="__('chat.title')" :description="__('chat.sub')">
        <x-slot:actions>@can('settings.manage')
            <form method="POST" action="{{ route('chat.toggle') }}">@csrf<x-ui.button variant="secondary" :block="false">{{ $enabled ? __('chat.turn_off') : __('chat.turn_on') }}</x-ui.button></form>
        @endcan</x-slot:actions>
    </x-ui.page-header>

    @unless ($enabled)<x-ui.alert type="warning">{{ __('chat.is_off') }}</x-ui.alert>@endunless

    @if ($rows->isEmpty())
        <div class="card"><x-ui.empty icon="bell" :title="__('chat.empty')" :text="__('chat.empty_text')" /></div>
    @else
        <ul class="card divide-y divide-line" x-data x-init="setTimeout(() => location.reload(), 30000)">
            @foreach ($rows as $row)
                @php($o = $orders[$row->order_id])
                @php($m = $last[$row->last_id])
                <li>
                    <a href="{{ route('chat.show', $o->id) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-surface-2">
                        <div class="min-w-0">
                            <p class="font-semibold">#{{ $o->number }} · {{ $o->customer_name ?: __('chat.guest') }} @if ($o->table_name)<span class="text-sm font-normal text-muted">· {{ table_label($o->table_name) }}</span>@endif</p>
                            <p class="truncate text-sm text-muted"><span>{{ $m->sender === 'staff' ? __('chat.you').': ' : '' }}</span>{{ $m->body }}</p>
                        </div>
                        <div class="shrink-0 text-end">
                            <p class="text-xs text-muted">{{ $m->created_at->diffForHumans() }}</p>
                            @if ($row->unread > 0)<x-ui.badge tone="danger">{{ $row->unread }}</x-ui.badge>@endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
