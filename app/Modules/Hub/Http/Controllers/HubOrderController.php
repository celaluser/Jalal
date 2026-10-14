<?php

namespace App\Modules\Hub\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Hub\Models\OrderChannel;
use App\Modules\Hub\Services\ChannelOrders;
use App\Modules\Orders\Exceptions\OrderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** The address another platform posts its orders to. The secret in the address says which restaurant and channel it is. */
class HubOrderController extends Controller
{
    public function store(Request $request, string $token, TenantContext $tenant, ChannelOrders $channels): JsonResponse
    {
        $channel = OrderChannel::allTenants()->with('restaurant')->where('token_hash', hash('sha256', $token))->first();

        if (! $channel || ! $channel->is_active || ! $channel->restaurant || $channel->restaurant->status !== 'active') {
            return response()->json(['message' => 'Unknown or paused channel.'], 404);
        }

        try {
            $result = $tenant->runAs($channel->restaurant, fn () => $channels->receive($channel, (array) $request->json()->all()));
        } catch (InvalidArgumentException $e) {
            [$code, $detail] = array_pad(explode(':', $e->getMessage(), 2), 2, '');
            $channel->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 190)])->save();

            return response()->json(['message' => 'The order could not be accepted.', 'code' => $code, 'detail' => $detail], 422);
        } catch (OrderException $e) {
            $channel->forceFill(['last_error' => 'order: '.$e->reason])->save();

            return response()->json(['message' => 'The order could not be placed.', 'code' => $e->reason], 422);
        }

        $order = $result['order'];

        return response()->json(['duplicate' => $result['duplicate'], 'order' => ['id' => $order->id, 'number' => $order->number, 'status' => $order->status, 'total_cents' => $order->total_cents]], $result['duplicate'] ? 200 : 201);
    }
}
