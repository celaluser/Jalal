<?php

namespace App\Modules\Marketing\Mail;

use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A marketing e-mail written by a restaurant. The text is Markdown with raw HTML stripped; {{name}} and
 * {{restaurant}} are filled in afterwards (escaped). Every message carries a one-click unsubscribe link.
 */
class CampaignMail extends Mailable
{
    public readonly string $unsubscribeUrl;

    public function __construct(public readonly Campaign $campaign, public readonly Restaurant $restaurant, public readonly Customer $customer, private readonly bool $preview = false)
    {
        $this->unsubscribeUrl = URL::signedRoute('marketing.unsubscribe', ['customer' => $customer->id]);
        $this->locale($customer->locale ?: $restaurant->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->preview ? '[Test] ' : '').str_replace(["\r", "\n"], ' ', $this->fill($this->campaign->subject, escape: false)));
    }

    public function content(): Content
    {
        $html = Str::markdown($this->campaign->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return new Content(view: 'marketing::mail.campaign', with: ['body' => $this->fill($html, escape: true), 'restaurant' => $this->restaurant, 'unsubscribeUrl' => $this->unsubscribeUrl, 'menuUrl' => $this->restaurant->publicUrl()]);
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    private function fill(string $text, bool $escape): string
    {
        $values = ['name' => $this->customer->name ?: __('marketing.there'), 'restaurant' => $this->restaurant->name];

        return preg_replace_callback('/\{\{\s*(name|restaurant)\s*\}\}/i', fn ($m) => $escape ? e($values[strtolower($m[1])]) : $values[strtolower($m[1])], $text);
    }
}
