<div class="space-y-3">
    @foreach ($ticket->replies as $reply)
        <div @class(['rounded-xl p-4 text-sm', 'bg-brand-50 ring-1 ring-brand-100 dark:bg-gray-800 dark:ring-gray-700' => $reply->is_staff, 'bg-white ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800' => ! $reply->is_staff])>
            <p class="mb-1 text-xs text-gray-500"><strong>{{ $reply->is_staff ? __('support.staff') : ($reply->user?->name ?? '—') }}</strong> · {{ $reply->created_at->toDayDateTimeString() }}</p>
            {{-- Plain text with line breaks: user messages are never rendered as markup --}}
            <div class="whitespace-pre-line break-words">{{ $reply->body }}</div>
        </div>
    @endforeach
</div>
