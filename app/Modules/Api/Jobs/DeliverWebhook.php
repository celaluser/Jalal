<?php

namespace App\Modules\Api\Jobs;

use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Services\UrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * POSTs one webhook. The body is JSON; the signature header is `t=<unix time>,v1=<hmac-sha256 of "<t>.<body>" with the endpoint secret>`,
 * so receivers can verify the sender and reject replays. Five tries with growing pauses; 15 failed deliveries in a row switch the endpoint off.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $deliveryId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 3600];
    }

    public function handle(UrlGuard $guard): void
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()->find($this->deliveryId);
        $endpoint = $delivery ? WebhookEndpoint::withoutGlobalScopes()->find($delivery->webhook_endpoint_id) : null;

        if (! $delivery || ! $endpoint || ! $endpoint->is_active || $delivery->status === 'sent') {
            return;
        }

        $delivery->increment('attempts');
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $time = time();

        try {
            if (! $guard->allowed($endpoint->url)) {
                throw new RuntimeException('URL not allowed');
            }

            $options = ['allow_redirects' => false];

            if ($pin = $guard->pin($endpoint->url)) {
                // Connect to the address that was just checked, not to whatever DNS answers a moment later.
                $options['curl'] = [CURLOPT_RESOLVE => ["{$pin['host']}:{$pin['port']}:{$pin['ip']}"]];
            }

            $response = Http::timeout(8)->withOptions($options)->withHeaders([
                'Content-Type' => 'application/json', 'User-Agent' => 'QRMenu-Webhook/1', 'X-Webhook-Id' => $delivery->uuid, 'X-Webhook-Event' => $delivery->event,
                'X-Webhook-Signature' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, $endpoint->secret),
            ])->withBody($body, 'application/json')->post($endpoint->url);

            $delivery->forceFill(['response_code' => $response->status()])->save();

            if (! $response->successful()) {
                throw new RuntimeException('HTTP '.$response->status());
            }

            $delivery->forceFill(['status' => 'sent', 'error' => null])->save();
            $endpoint->forceFill(['failures' => 0])->save();
        } catch (Throwable $e) {
            $delivery->forceFill(['error' => mb_substr($e->getMessage(), 0, 200)])->save();

            if ($this->attempts() >= $this->tries) {
                $this->giveUp($delivery, $endpoint);

                return;
            }

            $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
        }
    }

    private function giveUp(WebhookDelivery $delivery, WebhookEndpoint $endpoint): void
    {
        $delivery->forceFill(['status' => 'failed'])->save();
        $endpoint->increment('failures');

        if ($endpoint->fresh()->failures >= WebhookEndpoint::MAX_FAILURES) {
            $endpoint->forceFill(['is_active' => false])->save();
        }
    }
}
