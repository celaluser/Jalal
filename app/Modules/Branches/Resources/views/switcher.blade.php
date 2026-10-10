{{-- Branch picker for staff. Shown only when the restaurant has branches and the user is not fixed to one. --}}
@php($context = app(\App\Modules\Branches\Services\BranchContext::class))
@if ($context->enabled() && auth()->user()?->restaurant_id)
    @php($list = $context->available())
    @php($current = $context->current())
    <form method="POST" action="{{ route('branches.switch') }}" class="mb-4 flex items-center gap-2">@csrf
        <x-ui.icon name="store" size="4" class="text-muted" />
        <label for="branch-switch" class="sr-only">{{ __('branches.switcher') }}</label>
        @if (auth()->user()->branch_id)
            <span class="text-sm font-medium">{{ $current?->name }}</span>
        @else
            <select id="branch-switch" name="branch" class="field !w-auto !py-1.5 text-sm" onchange="this.form.submit()">
                <option value="all" @selected(! $current)>{{ __('branches.all') }}</option>
                @foreach ($list as $b)<option value="{{ $b->id }}" @selected($current?->id === $b->id)>{{ $b->name }}</option>@endforeach
            </select>
            <noscript><button class="btn btn-secondary btn-sm">{{ __('branches.switcher') }}</button></noscript>
        @endif
    </form>
@endif
