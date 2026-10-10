<div class="space-y-4">
    @foreach ($ticket->replies as $reply)
        <div @class(['flex gap-3', 'flex-row-reverse' => $reply->is_staff])>
            <x-ui.avatar :name="$reply->is_staff ? __('support.staff') : ($reply->user?->name ?? '?')" size="9" />
            <div @class(['max-w-[85%] rounded-2xl px-4 py-3 text-sm', 'rounded-ss-md bg-surface ring-1 ring-line' => ! $reply->is_staff, 'rounded-se-md bg-brand-50 ring-1 ring-brand-200 dark:bg-brand-900/20 dark:ring-brand-800' => $reply->is_staff])>
                <p class="mb-1 text-xs text-muted"><strong class="text-fg">{{ $reply->is_staff ? __('support.staff') : ($reply->user?->name ?? '—') }}</strong> · {{ $reply->created_at->diffForHumans() }}</p>
                {{-- Plain text with line breaks: user messages are never rendered as markup --}}
                <div class="whitespace-pre-line break-words">{{ $reply->body }}</div>
            </div>
        </div>
    @endforeach
</div>
