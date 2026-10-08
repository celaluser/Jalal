<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Services\AiManager;
use App\Modules\Marketing\Models\Review;
use App\Modules\Marketing\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** What guests said after their meal. */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request): View
    {
        $filter = $request->query('filter');
        $list = Review::with('order')->when($filter === 'low', fn ($q) => $q->where('rating', '<=', Review::LOW))
            ->when($filter === 'unanswered', fn ($q) => $q->whereNull('replied_at'))->orderByDesc('id')->paginate(20)->withQueryString();

        return view('marketing::reviews.index', [
            'reviews' => $list, 'summary' => $this->reviews->summary(), 'filter' => $filter,
            'lowOpen' => Review::where('rating', '<=', Review::LOW)->whereNull('replied_at')->count(),
            'canManage' => $request->user()->can('marketing.manage'), 'aiEnabled' => app(AiManager::class)->configured(),
        ]);
    }

    public function reply(Request $request, int $review): RedirectResponse
    {
        $model = Review::findOrFail($review);
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:1000']]);
        $reply = trim(strip_tags((string) ($data['reply'] ?? '')));
        $model->update(['reply' => $reply !== '' ? $reply : null, 'replied_at' => $reply !== '' ? now() : null]);

        return back()->with('status', __('admin.saved'));
    }

    public function toggle(int $review): RedirectResponse
    {
        $model = Review::findOrFail($review);
        $model->update(['is_public' => ! $model->is_public]);

        return back()->with('status', __('admin.saved'));
    }
}
