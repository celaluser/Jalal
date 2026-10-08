<?php

namespace App\Modules\Orders\Services;

use App\Modules\Core\Services\SettingsService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Browser push messages to a guest about their order ("it is ready"). One VAPID key pair for the whole platform,
 * made the first time it is needed and kept in the settings (the private half encrypted). It never throws.
 */
class PushNotifier
{
    public function __construct(private readonly SettingsService $settings) {}

    public function configured(): bool
    {
        return class_exists(WebPush::class) && function_exists('openssl_pkey_new') && $this->keys() !== null;
    }

    public function publicKey(): ?string
    {
        return $this->keys()['public'] ?? null;
    }

    /** @return array{public: string, private: string}|null */
    private function keys(): ?array
    {
        $public = $this->settings->get('push.vapid_public');
        $private = $this->settings->get('push.vapid_private');

        if (! $public || ! $private) {
            try {
                $made = VAPID::createVapidKeys();
                $this->settings->set('push.vapid_public', $made['publicKey']);
                $this->settings->set('push.vapid_private', $made['privateKey'], encrypt: true);

                return ['public' => $made['publicKey'], 'private' => $made['privateKey']];
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        }

        return ['public' => $public, 'private' => $private];
    }

    public function subscribe(Order $order, string $endpoint, string $p256dh, string $auth): void
    {
        PushSubscription::updateOrCreate(['order_id' => $order->id, 'endpoint' => $endpoint], ['p256dh' => $p256dh, 'auth' => $auth]);
        $order->forceFill(['notify_channel' => 'push'])->saveQuietly();
    }

    /** @return bool true when at least one subscription accepted the message */
    public function send(Order $order, string $title, string $body, string $url): bool
    {
        $subs = PushSubscription::where('order_id', $order->id)->get();
        $keys = $subs->isEmpty() ? null : $this->keys();

        if (! $keys) {
            return false;
        }

        try {
            $push = new WebPush(['VAPID' => ['subject' => config('app.url'), 'publicKey' => $keys['public'], 'privateKey' => $keys['private']]], ['TTL' => 3600], 8);
            $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

            foreach ($subs as $s) {
                $push->queueNotification(Subscription::create(['endpoint' => $s->endpoint, 'publicKey' => $s->p256dh, 'authToken' => $s->auth, 'contentEncoding' => 'aes128gcm']), $payload);
            }

            $sent = false;

            foreach ($push->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent = true;
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::where('order_id', $order->id)->where('endpoint', $report->getEndpoint())->delete();
                }
            }

            return $sent;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
