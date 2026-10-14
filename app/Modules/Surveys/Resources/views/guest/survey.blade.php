<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $survey->title }} · {{ $restaurant->name }}</title>
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css'])
</head>
<body class="menu-page min-h-screen p-5">
<main class="mx-auto max-w-md space-y-5 pt-4">
    <a href="{{ $base ?: '/' }}" class="menu-muted text-sm underline">← {{ $restaurant->name }}</a>
    <h1 class="display text-2xl font-bold">{{ $survey->title }}</h1>

    @if ($done)
        <section class="menu-card p-5 text-center" role="status">
            <p class="display text-xl font-bold">{{ $survey->thank_you ?: __('surveys.thanks') }}</p>
        </section>
    @else
        @if ($survey->intro)<p class="menu-muted">{{ $survey->intro }}</p>@endif
        @error('survey')<p class="menu-card px-3 py-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
        <form method="POST" action="{{ $base }}/survey/{{ $survey->slug }}" class="space-y-4">@csrf
            @if ($orderToken)<input type="hidden" name="o" value="{{ $orderToken }}">@endif
            @foreach ($survey->questions as $q)
                @php($field = 'a.'.$q['id'])
                <fieldset class="menu-card space-y-3 p-4">
                    <legend class="px-1 font-semibold">{{ $q['label'] }}@if ($q['required']) <span class="text-red-600" aria-hidden="true">*</span>@endif</legend>
                    @if ($q['type'] === 'rating')
                        <div class="flex justify-between gap-1" role="radiogroup">
                            @foreach (range(1, 5) as $n)
                                <label class="flex-1 cursor-pointer text-center"><input class="peer sr-only" type="radio" name="a[{{ $q['id'] }}]" value="{{ $n }}" @checked((string) old($field) === (string) $n)><span class="menu-surface block rounded-xl border menu-line py-2.5 text-lg font-bold peer-checked:menu-accent peer-focus-visible:ring-2">{{ $n }}</span></label>
                            @endforeach
                        </div>
                    @elseif ($q['type'] === 'nps')
                        <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-11" role="radiogroup">
                            @foreach (range(0, 10) as $n)
                                <label class="cursor-pointer text-center"><input class="peer sr-only" type="radio" name="a[{{ $q['id'] }}]" value="{{ $n }}" @checked((string) old($field) === (string) $n)><span class="menu-surface block rounded-lg border menu-line py-2 text-sm font-bold peer-checked:menu-accent peer-focus-visible:ring-2">{{ $n }}</span></label>
                            @endforeach
                        </div>
                        <div class="menu-muted flex justify-between text-xs"><span>{{ __('surveys.nps_low') }}</span><span>{{ __('surveys.nps_high') }}</span></div>
                    @elseif ($q['type'] === 'choice')
                        <div class="space-y-2">
                            @foreach ($q['options'] as $o)
                                <label class="menu-surface flex cursor-pointer items-center gap-3 rounded-xl border menu-line px-3 py-2.5"><input type="radio" name="a[{{ $q['id'] }}]" value="{{ $o }}" @checked(old($field) === $o)> <span>{{ $o }}</span></label>
                            @endforeach
                        </div>
                    @else
                        <textarea name="a[{{ $q['id'] }}]" rows="3" maxlength="1000" class="menu-card w-full px-3 py-2.5">{{ old($field) }}</textarea>
                    @endif
                    @error($field)<p class="text-sm font-medium text-red-600" role="alert">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach
            <button class="menu-btn w-full !py-3.5">{{ __('surveys.send') }}</button>
        </form>
    @endif
</main>
</body>
</html>
