<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Services\Chat;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The restaurant's side: an inbox of conversations (newest first, unread counted) and a reply screen per order. */
class StaffChatController extends Controller
{
    public function __construct(private readonly Chat $chat) {}

    public function index(Request $request): View
    {
        $branchId = $request->user()->branch_id;
        $rows = ChatMessage::selectRaw('order_id, MAX(id) as last_id, SUM(CASE WHEN sender = ? AND staff_read_at IS NULL THEN 1 ELSE 0 END) as unread', ['guest'])
            ->when($branchId, fn ($q) => $q->whereIn('order_id', Order::where('branch_id', $branchId)->select('id')))
            ->groupBy('order_id')->orderByDesc('last_id')->limit(100)->get();

        $orders = Order::whereIn('id', $rows->pluck('order_id'))->get()->keyBy('id');
        $last = ChatMessage::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        return view('chat::inbox', [
            'rows' => $rows->filter(fn ($r) => $orders->has($r->order_id))->values(), 'orders' => $orders, 'last' => $last,
            'enabled' => $this->chat->enabled($request->user()->restaurant),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->guardBranch($request, $order);
        $this->chat->markReadByStaff($order);

        return view('chat::conversation', ['order' => $order, 'messages' => $this->chat->since($order, 0), 'open' => $this->chat->open($order)]);
    }

    public function messages(Request $request, Order $order): JsonResponse
    {
        $this->guardBranch($request, $order);
        $messages = $this->chat->since($order, max(0, (int) $request->query('after', 0)));

        if ($messages !== []) {
            $this->chat->markReadByStaff($order);
        }

        return response()->json(['messages' => $messages])->header('Cache-Control', 'no-store');
    }

    public function reply(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $this->guardBranch($request, $order);
        $data = $request->validate(['body' => ['required', 'string', 'max:'.Chat::MAX_LENGTH]]);
        $message = $this->chat->send($order, 'staff', $data['body'], $request->user());
        $this->chat->markReadByStaff($order);

        return $request->expectsJson()
            ? response()->json(['message' => $message ? ['id' => $message->id, 'from' => 'staff', 'text' => $message->body, 'at' => $message->created_at->toIso8601String()] : null], 201)
            : back();
    }

    public function toggle(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $this->chat->setEnabled($restaurant, ! $this->chat->enabled($restaurant));

        return back()->with('status', __('admin.saved'));
    }

    /** Staff fixed to one branch only reach the conversations of that branch. */
    private function guardBranch(Request $request, Order $order): void
    {
        $branchId = $request->user()->branch_id;

        abort_if($branchId !== null && $order->branch_id !== null && (int) $order->branch_id !== (int) $branchId, 404);
    }
}
