<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\GiftCard;
use App\Modules\Marketing\Services\GiftCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Issue gift cards (sold at the till or given away); guests spend them like a code at checkout. */
class GiftCardController extends Controller
{
    public function __construct(private readonly GiftCards $cards) {}

    public function index(Request $request): View
    {
        return view('marketing::gifts.index', ['cards' => GiftCard::orderByDesc('id')->paginate(25), 'restaurant' => $request->user()->restaurant]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'], 'recipient_email' => ['nullable', 'email:rfc', 'max:190'],
            'note' => ['nullable', 'string', 'max:200'], 'valid_days' => ['nullable', 'integer', 'between:1,3650'],
        ]);
        $card = $this->cards->issue($request->user()->restaurant, (int) round((float) $data['amount'] * 100), $data['recipient_email'] ?? null, $data['note'] ?? null, isset($data['valid_days']) ? (int) $data['valid_days'] : null);

        return back()->with('status', __('marketing.gift_issued', ['code' => $card->code]));
    }

    public function toggle(int $card): RedirectResponse
    {
        $model = GiftCard::findOrFail($card);
        $model->update(['is_active' => ! $model->is_active]);

        return back();
    }
}
