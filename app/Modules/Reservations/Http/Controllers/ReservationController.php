<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Reservations\Models\Reservation;
use App\Modules\Reservations\Services\Availability;
use App\Modules\Reservations\Services\ReservationDeposits;
use App\Modules\Reservations\Services\ReservationService;
use App\Modules\Reservations\Services\ReservationSettings;
use App\Modules\Tables\Models\DiningTable;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Staff: the day's bookings, moving them along, seating at a table, taking a booking by phone, and the settings. */
class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $service, private readonly ReservationSettings $settings, private readonly Availability $availability) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;
        $tz = $this->availability->zone($restaurant);
        $date = $request->query('date');
        $day = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? CarbonImmutable::parse($date, $tz) : CarbonImmutable::now($tz);

        return view('reservations::staff.index', [
            'restaurant' => $restaurant, 'day' => $day, 'tz' => $tz, 's' => $this->settings->for($restaurant), 'active' => $this->settings->active($restaurant),
            'items' => Reservation::with('table')->whereBetween('starts_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])->orderBy('starts_at')->get(),
            'pending' => Reservation::where('status', 'pending')->where('starts_at', '>=', now())->count(),
            'tables' => DiningTable::where('is_active', true)->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'], 'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'], 'note' => ['nullable', 'string', 'max:300'], 'force' => ['nullable', 'boolean'],
        ]);

        try {
            $this->service->book($restaurant, $data, 'staff');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['time' => __('reservations.error_'.$e->getMessage())]);
        }

        return redirect()->route('reservations.index', ['date' => $data['date']])->with('status', __('reservations.created'));
    }

    /** The restaurant paid a "refund due" deposit back by hand (in the gateway's dashboard). */
    public function depositRefunded(int $reservation): RedirectResponse
    {
        app(ReservationDeposits::class)->markRefunded(Reservation::findOrFail($reservation));

        return back();
    }

    public function status(Request $request, int $reservation): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $res = Reservation::findOrFail($reservation);
        $data = $request->validate(['status' => ['required', Rule::in(Reservation::STATUSES)], 'table_id' => ['nullable', 'integer']]);

        try {
            if ($data['status'] === 'confirmed') {
                $this->service->confirm($restaurant, $res, $data['table_id'] ?? null);
            } else {
                $this->service->setStatus($restaurant, $res, $data['status']);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reservation' => __('reservations.error_'.$e->getMessage())]);
        }

        return back();
    }

    public function table(Request $request, int $reservation): RedirectResponse
    {
        $res = Reservation::findOrFail($reservation);
        $id = $request->validate(['table_id' => ['nullable', 'integer']])['table_id'] ?? null;
        abort_if($id !== null && ! DiningTable::where('id', $id)->exists(), 404);
        $res->update(['table_id' => $id]);

        return back();
    }

    public function settings(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('reservations::staff.settings', ['s' => $this->settings->for($restaurant), 'allowed' => app(LimitGuard::class)->hasFeature($restaurant, 'reservations'), 'url' => $restaurant->publicUrl('reserve')]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->settings->save($request->user()->restaurant, $request->validate($this->settings->rules()));

        return back()->with('status', __('admin.saved'));
    }
}
