<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\Exceptions\GatewayException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class BaseGateway implements PaymentGatewayInterface
{
    public function supportsCurrency(string $currency): bool
    {
        return true;
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson();
    }

    /**
     * Decode a gateway response or throw a safe, secret-free exception.
     *
     * @return array<string, mixed>
     */
    protected function json(Response $response, string $action): array
    {
        if (! $response->successful()) {
            $detail = $response->json('error.message') ?? $response->json('message') ?? $response->json('error_description');
            $detail = is_string($detail) ? ': '.Str::limit($detail, 160) : '';

            throw new GatewayException("{$this->name()}: {$action} failed (HTTP {$response->status()}){$detail}");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new GatewayException("{$this->name()}: {$action} returned an unreadable response.");
        }

        return $data;
    }

    protected function minor(Invoice $invoice): int
    {
        return Money::toMinor($invoice->total, $invoice->currency_code);
    }

    protected function description(Invoice $invoice): string
    {
        return $invoice->items[0]['description'] ?? $invoice->number;
    }

    protected function buyerEmail(Invoice $invoice): ?string
    {
        return $invoice->billing['buyer']['email'] ?? null;
    }

    protected function signatureMatches(string $expected, string $given): bool
    {
        return $given !== '' && hash_equals($expected, $given);
    }
}
