<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('customer.account_title') }} · {{ $restaurant->name }}</title>
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css'])
</head>
<body class="menu-page min-h-screen p-5">
    <main class="mx-auto max-w-md space-y-5 pt-6">
        <a href="{{ $base ?: '/' }}" class="menu-muted text-sm underline">← {{ $restaurant->name }}</a>
        <h1 class="display text-2xl font-bold">{{ __('customer.account_title') }}</h1>
        @if (session('status'))<p class="menu-card px-3 py-2 text-sm" role="status">{{ session('status') }}</p>@endif

        @if (! $customer)
            @if (session('link_sent'))
                <p class="menu-card px-4 py-3" role="status">{{ __('customer.account_link_sent') }}</p>
            @else
                <p class="menu-muted">{{ __('customer.account_intro') }}</p>
                <form method="POST" action="{{ $base }}/account/login" class="menu-card space-y-3 p-4">@csrf
                    <label for="email" class="block text-sm font-medium">{{ __('orders.email') }}</label>
                    <input id="email" name="email" type="email" required autocomplete="email" class="w-full rounded-xl border bg-transparent px-3 py-2.5 menu-line" value="{{ old('email') }}">
                    @error('email')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <button class="menu-btn w-full">{{ __('customer.account_send') }}</button>
                </form>
            @endif
        @else
            <form method="POST" action="{{ $base }}/account" class="menu-card space-y-3 p-4">@csrf @method('PUT')
                <p class="menu-muted text-sm" dir="ltr">{{ $customer->email }}</p>
                <label class="block text-sm font-medium" for="name">{{ __('orders.name') }}</label>
                <input id="name" name="name" value="{{ old('name', $customer->name) }}" maxlength="80" class="w-full rounded-xl border bg-transparent px-3 py-2.5 menu-line">
                <label class="block text-sm font-medium" for="phone">{{ __('orders.phone') }}</label>
                <input id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" maxlength="40" inputmode="tel" class="w-full rounded-xl border bg-transparent px-3 py-2.5 menu-line">
                @error('phone')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <button class="menu-btn w-full">{{ __('admin.save') }}</button>
            </form>

            <section aria-label="{{ __('customer.account_orders') }}">
                <h2 class="display mb-2 text-lg font-bold">{{ __('customer.account_orders') }}</h2>
                @forelse ($orders as $o)
                    <a href="{{ $base }}/order/{{ $o->token }}" class="menu-card mb-2 flex items-center justify-between gap-3 p-3.5">
                        <span><span class="block font-semibold">{{ $o->label() }}</span><span class="menu-muted block text-sm">{{ $o->created_at->toFormattedDateString() }} · {{ __('orders.type_'.$o->type) }}</span></span>
                        <span class="tnum text-end"><span class="block font-semibold">{{ $restaurant->money($o->total_cents / 100) }}</span><span class="menu-muted block text-xs">{{ __('orders.status_'.$o->status) }}</span></span>
                    </a>
                @empty
                    <p class="menu-muted">{{ __('customer.account_no_orders') }}</p>
                @endforelse
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <form method="POST" action="{{ $base }}/account/logout">@csrf<button class="menu-chip">{{ __('customer.account_logout') }}</button></form>
                <a class="text-sm underline" href="{{ $base }}/account/export">{{ __('customer.account_export') }}</a>
                <form method="POST" action="{{ $base }}/account" onsubmit="return confirm('{{ __('customer.account_delete_confirm') }}')">@csrf @method('DELETE')<button class="text-sm underline text-red-600">{{ __('customer.account_delete') }}</button></form>
            </div>
        @endif
    </main>
</body>
</html>
