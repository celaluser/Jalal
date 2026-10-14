<?php

namespace App\Modules\Core\Mail;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a templated e-mail without ever letting a mail outage break the action that triggered it
 * (a payment, a registration). Failures are reported to the log; switched-off templates are skipped.
 */
final class SafeMail
{
    public static function send(mixed $to, TemplatedMail $mail): bool
    {
        if (! $mail->shouldSend() || ! $to) {
            return false;
        }

        try {
            Mail::to($to)->send($mail);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
