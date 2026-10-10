<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('reservations.book_title') }} · {{ $restaurant->name }}</title>
    <style>{!! $themeCss !!}</style>
    @vite(['resources/css/app.css'])
</head>
<body class="menu-page min-h-screen p-5">
<main class="mx-auto max-w-md space-y-5 pt-4" x-data="reserve(@js(['url' => $base.'/reserve/slots', 'date' => old('date', $today), 'party' => (int) old('party_size', 2), 'time' => old('time', '')]))">
    <a href="{{ $base ?: '/' }}" class="menu-muted text-sm underline">← {{ $restaurant->name }}</a>
    <h1 class="display text-2xl font-bold">{{ __('reservations.book_title') }}</h1>
    <form method="POST" action="{{ $base }}/reserve" class="menu-card space-y-4 p-4">@csrf
        <div class="grid grid-cols-2 gap-3">
            <div><label for="date" class="mb-1 block text-sm font-semibold">{{ __('reservations.date') }}</label>
                <input id="date" type="date" name="date" x-model="date" x-on:change="load()" min="{{ $today }}" max="{{ $last }}" required class="menu-card w-full px-3 py-2.5"></div>
            <div><label for="party" class="mb-1 block text-sm font-semibold">{{ __('reservations.party') }}</label>
                <input id="party" type="number" name="party_size" x-model.number="party" x-on:change="load()" min="1" max="{{ $s['max_party'] }}" required class="menu-card w-full px-3 py-2.5"></div>
        </div>
        <div>
            <p class="mb-1 text-sm font-semibold">{{ __('reservations.time') }}</p>
            <p class="menu-muted text-sm" x-show="!loading && slots.length === 0">{{ __('reservations.no_slots') }}</p>
            <div class="flex flex-wrap gap-2" role="radiogroup">
                <template x-for="t in slots" :key="t"><label class="cursor-pointer"><input type="radio" name="time" :value="t" x-model="time" class="peer sr-only" required><span class="menu-chip !px-3.5 !py-2 !text-sm peer-checked:!border-current peer-checked:font-bold" x-text="t"></span></label></template>
            </div>
            @error('time')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
        </div>
        <div><label for="name" class="mb-1 block text-sm font-semibold">{{ __('orders.name') }}</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="80" autocomplete="name" class="menu-card w-full px-3 py-2.5">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label for="phone" class="mb-1 block text-sm font-semibold">{{ __('orders.phone_label') }}</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="40" autocomplete="tel" class="menu-card w-full px-3 py-2.5" dir="ltr">@error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="email" class="mb-1 block text-sm font-semibold">{{ __('orders.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" class="menu-card w-full px-3 py-2.5" dir="ltr">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        </div>
        <p class="menu-muted text-xs">{{ __('reservations.contact_help') }}</p>
        <div><label for="note" class="mb-1 block text-sm font-semibold">{{ __('reservations.note') }}</label><textarea id="note" name="note" rows="2" maxlength="300" class="menu-card w-full px-3 py-2.5">{{ old('note') }}</textarea></div>
        @if ($deposit)
            <div class="menu-card space-y-2 p-3 text-sm">
                <p class="font-semibold">{{ __('reservations.deposit_title', ['amount' => $deposit['per_person']]) }}</p>
                <p class="menu-muted">{{ __('reservations.deposit_terms', ['hours' => $deposit['refund_hours'], 'minutes' => $deposit['hold']]) }}</p>
                @if (count($deposit['gateways']) > 1)
                    <label class="block"><span class="mb-1 block font-semibold">{{ __('reservations.deposit_pay_with') }}</span>
                        <select name="gateway" class="menu-card w-full px-3 py-2.5">@foreach ($deposit['gateways'] as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select></label>
                @endif
            </div>
        @endif
        <button class="menu-btn w-full !py-3.5" :disabled="!time">{{ $deposit ? __('reservations.book_and_pay') : __('reservations.book') }}</button>
    </form>
</main>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('reserve', (cfg) => ({
            date: cfg.date, party: cfg.party, time: cfg.time, slots: [], loading: false,
            init() { this.load(); },
            async load() {
                if (!this.date || !this.party) { this.slots = []; return; }
                this.loading = true;
                try { const r = await fetch(cfg.url + '?date=' + encodeURIComponent(this.date) + '&party=' + this.party, { headers: { Accept: 'application/json' } }); this.slots = r.ok ? (await r.json()).slots : []; } catch (e) { this.slots = []; }
                if (!this.slots.includes(this.time)) { this.time = ''; }
                this.loading = false;
            },
        }));
    });
</script>
</body>
</html>
