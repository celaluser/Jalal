<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Orders\Services\RestaurantGateways;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Reservations\Services\Availability;
use App\Modules\Reservations\Services\ReservationDeposits;
use App\Modules\Reservations\Services\ReservationService;
use App\Modules\Reservations\Services\ReservationSettings;
use App\Modules\Storefront\Services\MenuLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

/** The guest's side: pick a day and party size, see the free times, book, and manage the booking from a private link. */
class GuestReservationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant, private readonly ReservationSettings $settings, private readonly Availability $availability,
        private readonly ReservationService $service, private readonly MenuLocale $locales, private readonly ThemeRegistry $themes,
        private readonly ReservationDeposits $deposits, private readonly RestaurantGateways $gateways,
    ) {}

    public function form(Request $request): View
    {
        $restaurant = $this->open($request);
        $s = $this->settings->for($restaurant);

        return $this->view('storefront-reserve', $request, $restaurant, ['deposit' => (float) $s['deposit_per_person'] > 0 && $this->gateways->availableFor($restaurant) !== [] ? ['per_person' => $restaurant->money((float) $s['deposit_per_person']), 'gateways' => collect($this->gateways->availableFor($restaurant))->map(fn ($g) => $g->name())->all(), 'refund_hours' => (int) $s['deposit_refund_hours'], 'hold' => (int) $s['deposit_hold_minutes']] : null, 's' => $s, 'today' => now($this->availability->zone($restaurant))->toDateString(), 'last' => now($this->availability->zone($restaurant))->addDays($s['days_ahead'])->toDateString()]);
    }

    /** Free times for a day and party size, polled as the guest changes either. */
    public function slots(Request $request): JsonResponse
    {
        $restaurant = $this->open($request);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'party' => ['required', 'integer', 'min:1', 'max:100']]);

        return response()->json(['slots' => collect($this->availability->slots($restaurant, $data['date'], (int) $data['party']))->map(fn ($t) => $t->format('H:i'))->values()->all()])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $this->open($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.]{6,40}$/'], 'email' => ['nullable', 'email', 'max:190'],
            'gateway' => ['nullable', 'string', 'max:24'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'], 'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'], 'note' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $res = $this->service->book($restaurant, $data + ['locale' => app()->getLocale()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['time' => __('reservations.error_'.$e->getMessage())]);
        }

        // A deposit holds the table: straight to the payment page.
        if ($res->status === 'awaiting') {
            return $this->pay($request, $restaurant, $res, $data['gateway'] ?? null);
        }

        return redirect($this->base($request).'/reserve/'.$res->token);
    }

    /** (Re)start the deposit payment of a booking that is waiting for it. */
    public function payDeposit(Request $request): RedirectResponse
    {
        $restaurant = $this->tenant->get();
        $res = Reservation::where('token', (string) $request->route('token'))->firstOrFail();
        abort_unless($res->status === 'awaiting', 404);

        return $this->pay($request, $restaurant, $res, $request->input('gateway'));
    }

    /** The guest comes back from the hosted page. Whatever the URL says, the gateway is asked whether it was paid. */
    public function depositReturn(Request $request): RedirectResponse
    {
        $restaurant = $this->tenant->get();
        $res = Reservation::where('token', (string) $request->route('token'))->firstOrFail();
        $back = redirect($this->base($request).'/reserve/'.$res->token);

        if ($res->deposit_status === 'pending' && $res->deposit_gateway && ($gateway = $this->gateways->find($res->deposit_gateway))) {
            try {
                $n = $gateway->confirmReturn($request, $this->deposits->invoice($restaurant, $res), $this->gateways->config($restaurant, $res->deposit_gateway));

                if ($n !== null && $n->invoiceNumber === $res->deposit_reference) {
                    $this->deposits->finalize((string) $res->deposit_reference, $n);
                }
            } catch (GatewayException $e) {
                Log::warning('Reservation deposit return could not be confirmed', ['reservation' => $res->id, 'reason' => $e->getMessage()]);
            }
        }

        return $back;
    }

    private function pay(Request $request, $restaurant, Reservation $res, ?string $code): RedirectResponse
    {
        $available = array_keys($this->gateways->availableFor($restaurant));
        $code = in_array($code, $available, true) ? $code : ($available[0] ?? '');
        $base = $this->base($request);
        $root = $base !== '' ? url($base) : url('/');

        try {
            $url = $this->deposits->start($restaurant, $res, $code, fn (string $ref) => [$root.'/reserve/'.$res->token.'/pay/return', $root.'/reserve/'.$res->token, $root.'/pay/'.$code.'/webhook']);
        } catch (InvalidArgumentException $e) {
            // Could not even start: release the table straight away instead of holding it for nothing.
            $this->service->setStatus($restaurant, $res, 'cancelled');

            return redirect($base.'/reserve')->withInput()->withErrors(['time' => __('reservations.error_'.$e->getMessage())]);
        }

        return redirect()->away($url);
    }

    public function show(Request $request): View
    {
        $restaurant = $this->tenant->get();
        $res = Reservation::where('token', (string) $request->route('token'))->firstOrFail();

        return $this->view('storefront-reservation', $request, $restaurant, ['res' => $res, 'gateways' => collect($this->gateways->availableFor($restaurant))->map(fn ($g) => $g->name())->all(), 'when' => $res->starts_at->setTimezone($this->availability->zone($restaurant))]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $restaurant = $this->tenant->get();
        $res = Reservation::where('token', (string) $request->route('token'))->firstOrFail();

        try {
            $this->service->cancelByGuest($restaurant, $res);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reservation' => __('reservations.error_'.$e->getMessage())]);
        }

        return back();
    }

    private function open(Request $request)
    {
        $restaurant = $this->tenant->get();
        app()->setLocale($this->locales->resolve($request, $restaurant));
        abort_unless($this->settings->active($restaurant), 404);

        return $restaurant;
    }

    private function base(Request $request): string
    {
        return $request->route('restaurant') !== null ? '/'.config('tenancy.path_prefix').'/'.$request->route('restaurant') : '';
    }

    /** @param array<string, mixed> $extra */
    private function view(string $name, Request $request, $restaurant, array $extra): View
    {
        app()->setLocale($this->locales->resolve($request, $restaurant));

        return view('reservations::guest.'.$name, $extra + ['restaurant' => $restaurant, 'locale' => app()->getLocale(), 'dir' => $this->locales->isRtl(app()->getLocale()) ? 'rtl' : 'ltr', 'themeCss' => $this->themes->css($restaurant), 'base' => $this->base($request)]);
    }
}
