<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Marketing\Models\Review;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tenancy\Models\Restaurant;
use InvalidArgumentException;

/** Guest feedback after a finished order: one review per order, rated 1 to 5. */
class ReviewService
{
    public function __construct(private readonly MarketingSettings $settings) {}

    public function enabled(Restaurant $restaurant): bool
    {
        return (bool) $this->settings->get($restaurant, 'reviews_enabled');
    }

    public function forOrder(Order $order): ?Review
    {
        return Review::where('order_id', $order->id)->first();
    }

    public function canReview(Restaurant $restaurant, Order $order): bool
    {
        return $this->enabled($restaurant) && $order->status === OrderStatus::COMPLETED && $this->forOrder($order) === null;
    }

    /** @throws InvalidArgumentException code: closed | done | rating */
    public function submit(Restaurant $restaurant, Order $order, int $rating, ?string $comment, bool $public = true, ?int $nps = null): Review
    {
        if (! $this->enabled($restaurant) || $order->status !== OrderStatus::COMPLETED) {
            throw new InvalidArgumentException('closed');
        }

        if ($this->forOrder($order)) {
            throw new InvalidArgumentException('done');
        }

        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('rating');
        }

        $comment = trim(strip_tags((string) $comment));

        return Review::create([
            'order_id' => $order->id, 'customer_id' => $order->customer_id, 'rating' => $rating, 'nps' => $nps !== null ? max(0, min(10, $nps)) : null,
            'comment' => $comment !== '' ? mb_substr($comment, 0, 1000) : null,
            // Only the first name is ever shown next to a public review.
            'author' => $order->customer_name ? mb_substr(trim(explode(' ', trim($order->customer_name))[0]), 0, 40) : null,
            'is_public' => $public,
        ]);
    }

    /** Asks for feedback by e-mail once the order is completed. */
    public function requestByEmail(Restaurant $restaurant, Order $order): void
    {
        if (! $order->customer_email || ! $this->enabled($restaurant) || ! $this->settings->get($restaurant, 'review_request_email')) {
            return;
        }

        SafeMail::send($order->customer_email, new TemplatedMail('review_request', [
            'name' => $order->customer_name ?: '', 'restaurant' => $restaurant->name, 'number' => '#'.$order->number,
            'review_url' => $restaurant->publicUrl('order/'.$order->token),
        ], $order->locale));
    }

    /** @return array{average: float, count: int, distribution: array<int, int>} over all reviews of the current restaurant */
    public function summary(bool $public = false): array
    {
        $rows = Review::when($public, fn ($q) => $q->where('is_public', true))->selectRaw('rating, count(*) as n')->groupBy('rating')->pluck('n', 'rating');
        $count = (int) $rows->sum();
        $distribution = [];

        foreach ([5, 4, 3, 2, 1] as $star) {
            $distribution[$star] = (int) ($rows[$star] ?? 0);
        }

        return ['average' => $count ? round($rows->map(fn ($n, $r) => $n * $r)->sum() / $count, 1) : 0.0, 'count' => $count, 'distribution' => $distribution];
    }
}
