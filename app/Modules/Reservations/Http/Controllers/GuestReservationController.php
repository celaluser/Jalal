<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Reservations\Services\Availability;
use App\Modules\Reservations\Services\ReservationService;
use App\Modules\Reservations\Services\ReservationSettings;
use App\Modules\Storefront\Services\MenuLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** The guest's side: pick a day and party size, see the free times, book, and manage the booking from a private link. */
class GuestReservationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant, private readonly ReservationSettings $settings, private readonly Availability $availability,
        private readonly ReservationService $service, private readonly MenuLocale $locales, private readonly ThemeRegistry $themes,
    ) {}

    public function form(Request $request): View
    {
        $restaurant = $this->open($request);
        $s = $this->settings->for($restaurant);

        return $this->view('storefront-reserve', $request, $restaurant, ['s' => $s, 'today' => now($this->availability->zone($restaurant))->toDateString(), 'last' => now($this->availability->zone($restaurant))->addDays($s['days_ahead'])->toDateString()]);
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
            'party_size' => ['required', 'integer', 'min:1', 'max:100'], 'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'], 'note' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $res = $this->service->book($restaurant, $data + ['locale' => app()->getLocale()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['time' => __('reservations.error_'.$e->getMessage())]);
        }

        return redirect($this->base($request).'/reserve/'.$res->token);
    }

    public function show(Request $request): View
    {
        $restaurant = $this->tenant->get();
        $res = Reservation::where('token', (string) $request->route('token'))->firstOrFail();

        return $this->view('storefront-reservation', $request, $restaurant, ['res' => $res, 'when' => $res->starts_at->setTimezone($this->availability->zone($restaurant))]);
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
