<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('reservations.your_booking') }} · {{ $restaurant->name }}</title>
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css'])
</head>
<body class="menu-page min-h-screen p-5">
<main class="mx-auto max-w-md space-y-4 pt-6">
    <a href="{{ $base ?: '/' }}" class="menu-muted text-sm underline">← {{ $restaurant->name }}</a>
    <h1 class="display text-2xl font-bold">{{ __('reservations.your_booking') }}</h1>
    @error('reservation')<p class="menu-card px-3 py-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    <section class="menu-card space-y-2 p-5">
        <p class="display text-xl font-bold">{{ $when->isoFormat('dddd D MMMM') }}</p>
        <p class="display text-3xl font-extrabold">{{ $when->format('H:i') }}</p>
        <p>{{ trans_choice('reservations.people', $res->party_size, ['count' => $res->party_size]) }} · {{ $res->name }}</p>
        <p><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ in_array($res->status, ['confirmed', 'seated', 'completed'], true) ? 'menu-accent' : 'menu-surface border menu-line' }}">{{ __('reservations.status_'.$res->status) }}</span></p>
        @if ($res->status === 'pending')<p class="menu-muted text-sm">{{ __('reservations.pending_text') }}</p>@endif
        @if ($res->deposit_cents > 0)<p class="menu-muted text-sm">{{ __('reservations.deposit_line', ['amount' => $restaurant->money($res->deposit_cents / 100), 'state' => __('reservations.deposit_'.$res->deposit_status)]) }}</p>@endif
    </section>
    @if ($res->status === 'awaiting')
        <form method="POST" action="{{ $base }}/reserve/{{ $res->token }}/pay" class="menu-card space-y-3 p-4">@csrf
            <p class="text-sm">{{ __('reservations.awaiting_text', ['amount' => $restaurant->money($res->deposit_cents / 100)]) }}</p>
            @if (count($gateways) > 1)<select name="gateway" class="menu-card w-full px-3 py-2.5" aria-label="{{ __('reservations.deposit_pay_with') }}">@foreach ($gateways as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>@endif
            <button class="menu-btn w-full !py-3">{{ __('reservations.pay_deposit') }}</button>
        </form>
    @endif
    @if (in_array($res->status, ['awaiting', 'pending', 'confirmed'], true) && $res->starts_at->isFuture())
        <form method="POST" action="{{ $base }}/reserve/{{ $res->token }}/cancel" onsubmit="return confirm('{{ __('reservations.cancel_confirm') }}')">@csrf<button class="menu-btn menu-btn-quiet w-full">{{ __('reservations.cancel') }}</button></form>
    @endif
</main>
</body>
</html>
