<?php

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An e-mail whose subject and body come from an admin-editable template.
 */
class TemplatedMail extends Mailable
{
    /** @var array{subject: string, html: string}|null */
    private ?array $rendered;

    /**
     * @param  array<string, scalar|null>  $vars
     * @param  list<array{0: string, 1: string}>  $attachments  [binary contents, file name] pairs
     */
    public function __construct(
        public readonly string $templateKey,
        public readonly array $vars = [],
        public readonly ?string $forLocale = null,
        private readonly array $attachmentData = [],
    ) {
        $this->rendered = app(EmailTemplateRenderer::class)->render($templateKey, $vars, $forLocale);
    }

    /** False when the admin switched this (optional) e-mail off. */
    public function shouldSend(): bool
    {
        return $this->rendered !== null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered['subject'] ?? '');
    }

    public function content(): Content
    {
        return new Content(view: 'core::mail.layout', with: ['body' => $this->rendered['html'] ?? '']);
    }

    public function attachments(): array
    {
        return array_map(fn (array $a) => Attachment::fromData(fn () => $a[0], $a[1])->withMime('application/pdf'), $this->attachmentData);
    }
}
