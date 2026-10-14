<?php

namespace App\Modules\Inventory\Mail;

use App\Modules\Inventory\Models\Supplier;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A purchase order sent to a supplier, with the order as a PDF. Replies go to the restaurant's owner. */
class PurchaseOrderMail extends Mailable
{
    /** @param list<array<string, mixed>> $lines */
    public function __construct(public readonly Restaurant $restaurant, public readonly Supplier $supplier, public readonly array $lines, public readonly string $number, private readonly string $pdf, public readonly ?string $note = null)
    {
        $this->locale($restaurant->locale);
    }

    public function envelope(): Envelope
    {
        $reply = $this->restaurant->owner?->email;

        return new Envelope(subject: __('inventory.po_subject', ['restaurant' => str_replace(["\r", "\n"], ' ', $this->restaurant->name), 'number' => $this->number]), replyTo: $reply ? [$reply] : []);
    }

    public function content(): Content
    {
        return new Content(view: 'inventory::mail.po');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, 'purchase-order-'.$this->number.'.pdf')->withMime('application/pdf')];
    }
}
