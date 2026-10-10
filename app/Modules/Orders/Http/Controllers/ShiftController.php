<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Services\BranchContext;
use App\Modules\Orders\Models\Shift;
use App\Modules\Orders\Services\ShiftReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Cash drawer shifts: count the cash in, work, count it out, and see whether it adds up. */
class ShiftController extends Controller
{
    public function __construct(private readonly ShiftReport $report) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $mine = Shift::where('user_id', $user->id)->whereNull('closed_at')->first();
        $history = Shift::with('user')->whereNotNull('closed_at')->when(! $user->can('reports.view'), fn ($q) => $q->where('user_id', $user->id))->latest('closed_at')->limit(30)->get();

        return view('orders::shifts.index', [
            'restaurant' => $user->restaurant, 'mine' => $mine, 'live' => $mine ? $this->report->build($mine) : null, 'history' => $history,
            'open' => $user->can('reports.view') ? Shift::with('user')->whereNull('closed_at')->where('user_id', '!=', $user->id)->get() : collect(),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['opening' => ['required', 'numeric', 'min:0', 'max:99999999']]);

        if (Shift::where('user_id', $user->id)->whereNull('closed_at')->exists()) {
            return back()->withErrors(['shift' => __('orders.shift_already_open')]);
        }

        Shift::create(['user_id' => $user->id, 'branch_id' => app(BranchContext::class)->currentId($user), 'opened_at' => now(), 'opening_cents' => (int) round($data['opening'] * 100)]);

        return back()->with('status', __('orders.shift_opened'));
    }

    public function close(Request $request, Shift $shift): RedirectResponse
    {
        abort_unless($shift->user_id === $request->user()->id || $request->user()->can('reports.view'), 404);
        abort_unless($shift->isOpen(), 404);
        $data = $request->validate(['counted' => ['required', 'numeric', 'min:0', 'max:99999999'], 'note' => ['nullable', 'string', 'max:200']]);

        $summary = $this->report->build($shift);
        $shift->forceFill(['closed_at' => now(), 'closing_cents' => (int) round($data['counted'] * 100), 'expected_cents' => $summary['expected'], 'summary' => $summary, 'note' => $data['note'] ?? null])->save();

        return back()->with('status', __('orders.shift_closed'));
    }
}
