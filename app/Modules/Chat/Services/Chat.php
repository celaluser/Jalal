<?php

namespace App\Modules\Chat\Services;

use App\Models\User;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;

/** Conversations between guests and staff. One conversation per order; it stays open while the order is, and for a few hours after. */
class Chat
{
    public const HOURS_AFTER = 6;

    public const MAX_LENGTH = 500;

    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(Restaurant $restaurant): bool
    {
        return $this->settings->get('chat.enabled', '1', $restaurant->id) !== '0';
    }

    public function setEnabled(Restaurant $restaurant, bool $on): void
    {
        $this->settings->set('chat.enabled', $on ? '1' : '0', $restaurant->id);
    }

    /** Can the guest still write on this order? */
    public function open(Order $order): bool
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return false;
        }

        return $order->isOpen() || $order->updated_at->gt(now()->subHours(self::HOURS_AFTER));
    }

    public function send(Order $order, string $sender, string $body, ?User $by = null): ?ChatMessage
    {
        $body = trim(preg_replace('/\s+/u', ' ', strip_tags($body)));

        if ($body === '') {
            return null;
        }

        return ChatMessage::create([
            'order_id' => $order->id, 'sender' => $sender, 'user_id' => $by?->id, 'body' => mb_substr($body, 0, self::MAX_LENGTH),
            // A message is read by the person who wrote it.
            $sender === 'staff' ? 'staff_read_at' : 'guest_read_at' => now(),
        ]);
    }

    /** @return array<int, array{id: int, from: string, text: string, at: string}> */
    public function since(Order $order, int $afterId): array
    {
        return ChatMessage::where('order_id', $order->id)->where('id', '>', $afterId)->orderBy('id')->limit(100)->get()
            ->map(fn (ChatMessage $m) => ['id' => $m->id, 'from' => $m->sender, 'text' => $m->body, 'at' => $m->created_at->toIso8601String()])->all();
    }

    public function markReadByStaff(Order $order): void
    {
        ChatMessage::where('order_id', $order->id)->where('sender', 'guest')->whereNull('staff_read_at')->update(['staff_read_at' => now()]);
    }

    public function markReadByGuest(Order $order): void
    {
        ChatMessage::where('order_id', $order->id)->where('sender', 'staff')->whereNull('guest_read_at')->update(['guest_read_at' => now()]);
    }

    /** Messages from guests nobody on the staff has read yet. */
    public function unreadForStaff(?int $branchId = null): int
    {
        return ChatMessage::where('sender', 'guest')->whereNull('staff_read_at')
            ->when($branchId, fn ($q) => $q->whereIn('order_id', Order::where('branch_id', $branchId)->select('id')))->count();
    }
}
