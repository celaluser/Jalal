<?php

namespace App\Modules\Support\Services;

use App\Models\User;
use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Support\Models\Ticket;
use App\Modules\Support\Models\TicketReply;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/**
 * Ticket lifecycle. Status rules: a restaurant message (re)opens the ticket, a staff reply marks it
 * answered, closing is explicit. Notifications go out through the editable e-mail templates.
 */
class TicketService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function open(Restaurant $restaurant, User $user, string $subject, string $body, string $priority): Ticket
    {
        $ticket = DB::transaction(function () use ($restaurant, $user, $subject, $body, $priority) {
            $ticket = Ticket::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $user->id,
                'subject' => $subject,
                'priority' => $priority,
                'status' => 'open',
                'last_reply_at' => now(),
            ]);

            TicketReply::create(['ticket_id' => $ticket->id, 'restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'is_staff' => false, 'body' => $body]);

            return $ticket;
        });

        SafeMail::send($this->settings->get('general.support_email'), new TemplatedMail('ticket_new', [
            'restaurant' => $restaurant->name,
            'subject' => $subject,
            'message' => $body,
            'ticket_url' => route('admin.tickets.show', $ticket->id),
        ]));

        return $ticket;
    }

    public function reply(Ticket $ticket, User $user, string $body, bool $staff): TicketReply
    {
        $reply = DB::transaction(function () use ($ticket, $user, $body, $staff) {
            $reply = TicketReply::create(['ticket_id' => $ticket->id, 'restaurant_id' => $ticket->restaurant_id, 'user_id' => $user->id, 'is_staff' => $staff, 'body' => $body]);

            $ticket->forceFill(['status' => $staff ? 'answered' : 'open', 'closed_at' => null, 'last_reply_at' => now()])->save();

            return $reply;
        });

        if ($staff) {
            $owner = Restaurant::withTrashed()->with('owner')->find($ticket->restaurant_id)?->owner;

            SafeMail::send($owner, new TemplatedMail('ticket_reply', [
                'name' => $owner?->name ?? '',
                'subject' => $ticket->subject,
                'message' => $body,
                'ticket_url' => route('support.show', $ticket->id),
            ], $owner?->locale));
        }

        return $reply;
    }

    public function close(Ticket $ticket): Ticket
    {
        $ticket->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

        return $ticket;
    }

    public function reopen(Ticket $ticket): Ticket
    {
        $ticket->forceFill(['status' => 'open', 'closed_at' => null])->save();

        return $ticket;
    }
}
