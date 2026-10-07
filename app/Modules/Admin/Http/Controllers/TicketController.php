<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Support\Models\Ticket;
use App\Modules\Support\Services\TicketService;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $priority = $request->query('priority');
        $search = trim((string) $request->query('q'));

        return view('admin::tickets.index', [
            'tickets' => Ticket::allTenants()
                ->when(in_array($status, Ticket::STATUSES, true), fn ($q) => $q->where('status', $status))
                ->when(in_array($priority, Ticket::PRIORITIES, true), fn ($q) => $q->where('priority', $priority))
                ->when($search !== '', fn ($q) => $q->where('subject', 'like', '%'.addcslashes($search, '%_\\').'%'))
                // Needs-attention first: open before answered before closed, then newest activity.
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
                ->latest('last_reply_at')->paginate(25)->withQueryString(),
            'restaurants' => Restaurant::withTrashed()->pluck('name', 'id'),
            'filters' => ['status' => $status, 'priority' => $priority, 'q' => $search],
        ]);
    }

    public function show(int $ticket): View
    {
        $model = Ticket::allTenants()->with(['replies.user', 'user'])->findOrFail($ticket);

        return view('admin::tickets.show', ['ticket' => $model, 'restaurant' => Restaurant::withTrashed()->find($model->restaurant_id)]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        $this->tickets->reply(Ticket::allTenants()->findOrFail($ticket), $request->user(), $data['message'], staff: true);

        return back()->with('status', __('support.reply_sent'));
    }

    public function close(int $ticket): RedirectResponse
    {
        $this->tickets->close(Ticket::allTenants()->findOrFail($ticket));

        return back()->with('status', __('support.closed'));
    }

    public function reopen(int $ticket): RedirectResponse
    {
        $this->tickets->reopen(Ticket::allTenants()->findOrFail($ticket));

        return back()->with('status', __('admin.tickets.reopened'));
    }
}
