<?php

namespace App\Modules\Core\Mail;

/**
 * The e-mails the platform sends, with their variables and built-in default text (English).
 * The super admin can override subject/body per language; without an override the default is used,
 * so a fresh install never needs seeding. Add-ons add their own entries with register().
 *
 * In bodies use {{variable}} (no spaces inside links). Values are HTML-escaped on output.
 */
final class EmailTemplateRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $custom = null;

    /**
     * @param  array{label: string, required: bool, variables: list<string>, sample: array<string, string>, subject: string, body: string}  $definition
     */
    public static function register(string $key, array $definition): void
    {
        self::$custom[$key] = $definition;
    }

    /**
     * @return array<string, array{label: string, required: bool, variables: list<string>, sample: array<string, string>, subject: string, body: string}>
     */
    public static function all(): array
    {
        return array_merge(self::defaults(), self::$custom ?? []);
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function defaults(): array
    {
        return [
            'welcome' => [
                'label' => 'Welcome',
                'required' => false,
                'variables' => ['name', 'restaurant', 'login_url', 'app_name'],
                'sample' => ['name' => 'Ada', 'restaurant' => 'Bella Italia', 'login_url' => 'https://example.com/login', 'app_name' => 'QR Menu'],
                'subject' => 'Welcome to {{app_name}}',
                'body' => "Hi {{name}},\n\nWelcome to **{{app_name}}**! Your restaurant **{{restaurant}}** is ready to be set up.\n\n[Open your dashboard]({{login_url}})\n\nIf you need help, just reply to this e-mail.",
            ],
            'verify_email' => [
                'label' => 'E-mail verification',
                'required' => true,
                'variables' => ['name', 'action_url', 'app_name'],
                'sample' => ['name' => 'Ada', 'action_url' => 'https://example.com/email/verify/1/abc', 'app_name' => 'QR Menu'],
                'subject' => 'Verify your e-mail address',
                'body' => "Hi {{name}},\n\nPlease confirm your e-mail address to finish setting up your {{app_name}} account.\n\n[Verify e-mail address]({{action_url}})\n\nIf you did not create an account, you can ignore this message.",
            ],
            'password_reset' => [
                'label' => 'Password reset',
                'required' => true,
                'variables' => ['name', 'action_url', 'expires_minutes', 'app_name'],
                'sample' => ['name' => 'Ada', 'action_url' => 'https://example.com/reset-password/token', 'expires_minutes' => '60', 'app_name' => 'QR Menu'],
                'subject' => 'Reset your password',
                'body' => "Hi {{name}},\n\nWe received a request to reset your password. The link below is valid for {{expires_minutes}} minutes.\n\n[Reset password]({{action_url}})\n\nIf you did not ask for this, no action is needed.",
            ],
            'invoice_paid' => [
                'label' => 'Invoice paid',
                'required' => false,
                'variables' => ['name', 'restaurant', 'invoice_number', 'total', 'plan', 'app_name'],
                'sample' => ['name' => 'Ada', 'restaurant' => 'Bella Italia', 'invoice_number' => 'INV-2026-000042', 'total' => '29.00 USD', 'plan' => 'Pro (monthly)', 'app_name' => 'QR Menu'],
                'subject' => 'Payment received: invoice {{invoice_number}}',
                'body' => "Hi {{name}},\n\nThank you! We received your payment of **{{total}}** for **{{plan}}** ({{restaurant}}).\n\nYour invoice {{invoice_number}} is attached as a PDF.",
            ],
            'subscription_expiring' => [
                'label' => 'Subscription ending soon',
                'required' => false,
                'variables' => ['name', 'restaurant', 'plan', 'ends_at', 'days_left', 'billing_url', 'app_name'],
                'sample' => ['name' => 'Ada', 'restaurant' => 'Bella Italia', 'plan' => 'Pro', 'ends_at' => '2026-11-01', 'days_left' => '3', 'billing_url' => 'https://example.com/dashboard', 'app_name' => 'QR Menu'],
                'subject' => 'Your {{app_name}} plan ends in {{days_left}} days',
                'body' => "Hi {{name}},\n\nYour **{{plan}}** plan for **{{restaurant}}** ends on **{{ends_at}}**. Renew now so your menu and orders keep working.\n\n[Manage your plan]({{billing_url}})",
            ],
        ];
    }
}
