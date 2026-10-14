@php($announcements = \App\Modules\Support\Models\Announcement::live()->latest('id')->get())
@if ($announcements->isNotEmpty())
    <div class="space-y-2" role="region" aria-label="{{ __('support.announcements') }}">
        @foreach ($announcements as $a)
            {{-- Dismissal is remembered per browser, keyed by id + last edit so an updated notice shows again. --}}
            <div x-data="{ key: 'announcement-{{ $a->id }}-{{ $a->updated_at->timestamp }}', hidden: false, init() { try { this.hidden = localStorage.getItem(this.key) === '1'; } catch (e) {} } }"
                 x-show="!hidden" x-cloak role="status"
                 @class(['flex items-start justify-between gap-3 rounded-lg p-3 text-sm',
                    'bg-blue-50 text-blue-900 dark:bg-blue-950 dark:text-blue-100' => $a->level === 'info',
                    'bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-100' => $a->level === 'success',
                    'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-100' => $a->level === 'warning'])>
                <div>
                    <p class="font-semibold">{{ $a->title }}</p>
                    @if ($a->body)<div class="mt-0.5 [&_a]:underline">{!! markdown_safe($a->body) !!}</div>@endif
                </div>
                @if ($a->is_dismissible)
                    <button type="button" x-on:click="hidden = true; try { localStorage.setItem(key, '1'); } catch (e) {}" class="shrink-0 opacity-70 hover:opacity-100" aria-label="{{ __('support.dismiss') }}">✕</button>
                @endif
            </div>
        @endforeach
    </div>
@endif
