<?php

namespace App\Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Support\Models\Ticket;
use App\Modules\Support\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The restaurant's side of support. Tickets are tenant-scoped, so another restaurant's ticket id
 * simply 404s through route model binding.
 */
class SupportController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function index(): View
    {
        return view('support::index', ['tickets' => Ticket::latest('last_reply_at')->latest('id')->paginate(15)]);
    }

    public function create(): View
    {
        return view('support::create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
        ]);

        $ticket = $this->tickets->open($request->user()->restaurant, $request->user(), $data['subject'], $data['message'], $data['priority']);

        return redirect()->route('support.show', $ticket)->with('status', __('support.created'));
    }

    public function show(Ticket $ticket): View
    {
        return view('support::show', ['ticket' => $ticket->load(['replies.user'])]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        $this->tickets->reply($ticket, $request->user(), $data['message'], staff: false);

        return redirect()->route('support.show', $ticket)->with('status', __('support.reply_sent'));
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        $this->tickets->close($ticket);

        return redirect()->route('support.show', $ticket)->with('status', __('support.closed'));
    }
}
