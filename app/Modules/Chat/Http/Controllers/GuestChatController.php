<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Services\Chat;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The guest's side of the chat, from the order page. The secret order address is the key; polling keeps it simple on shared hosting. */
class GuestChatController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly Chat $chat) {}

    public function messages(Request $request): JsonResponse
    {
        [$restaurant, $order] = $this->find($request);
        $messages = $this->chat->since($order, max(0, (int) $request->query('after', 0)));

        if ($request->boolean('seen')) {
            $this->chat->markReadByGuest($order);
        }

        return response()->json(['messages' => $messages, 'open' => $this->chat->open($order)])->header('Cache-Control', 'no-store');
    }

    public function send(Request $request): JsonResponse
    {
        [, $order] = $this->find($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:'.Chat::MAX_LENGTH]]);

        abort_unless($this->chat->open($order), 403);

        $message = $this->chat->send($order, 'guest', $data['body']);

        return response()->json(['message' => $message ? ['id' => $message->id, 'from' => 'guest', 'text' => $message->body, 'at' => $message->created_at->toIso8601String()] : null], 201);
    }

    /** @return array{0: Restaurant, 1: Order} */
    private function find(Request $request): array
    {
        $restaurant = $this->tenant->get();
        abort_unless($this->chat->enabled($restaurant), 404);

        return [$restaurant, Order::where('token', (string) $request->route('token'))->firstOrFail()];
    }
}
