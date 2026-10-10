<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\CustomerSegment;
use App\Modules\Marketing\Services\SegmentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Saved audiences built from simple rules, for campaigns. */
class SegmentController extends Controller
{
    public function __construct(private readonly SegmentRules $rules) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;
        $segments = CustomerSegment::orderBy('name')->get()->map(function (CustomerSegment $s) use ($restaurant) {
            // How many guests match and agreed to marketing (the people a campaign can actually reach).
            $s->reach = $this->rules->apply(Customer::where('marketing_opt_in', true)->whereNull('unsubscribed_at'), (array) $s->rules, $restaurant)->count();

            return $s;
        });

        return view('marketing::segments.index', ['segments' => $segments, 'restaurant' => $restaurant]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'min_orders' => ['nullable', 'integer', 'min:0'], 'max_orders' => ['nullable', 'integer', 'min:0'], 'min_spent' => ['nullable', 'numeric', 'min:0'],
            'active_within_days' => ['nullable', 'integer', 'min:0'], 'inactive_for_days' => ['nullable', 'integer', 'min:0'], 'joined_within_days' => ['nullable', 'integer', 'min:0'],
            'birthday' => ['nullable', 'in:this_month,next_month'], 'tier' => ['nullable', 'in:bronze,silver,gold'],
        ]);
        abort_if(CustomerSegment::count() >= 50, 422);
        CustomerSegment::create(['name' => $data['name'], 'rules' => $this->rules->clean($data)]);

        return back()->with('status', __('marketing.segment_saved'));
    }

    public function destroy(int $segment): RedirectResponse
    {
        CustomerSegment::findOrFail($segment)->delete();

        return back()->with('status', __('marketing.segment_deleted'));
    }
}
