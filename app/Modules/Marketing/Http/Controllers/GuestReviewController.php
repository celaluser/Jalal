<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Services\ReviewService;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** The guest rates their order from its tracking page (the secret order link is the only credential needed). */
class GuestReviewController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly ReviewService $reviews) {}

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $this->tenant->get();
        // Read by name: the route also carries {restaurant}, and parameters reach controllers by position.
        $order = Order::where('token', (string) $request->route('token'))->firstOrFail();
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000'], 'is_public' => ['nullable', 'boolean']]);

        try {
            $this->reviews->submit($restaurant, $order, (int) $data['rating'], $data['comment'] ?? null, $request->boolean('is_public', true));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['review' => __('marketing.review_error_'.$e->getMessage())]);
        }

        return back()->with('review_thanks', true);
    }
}
