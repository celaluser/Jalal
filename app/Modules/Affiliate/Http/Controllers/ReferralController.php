<?php

namespace App\Modules\Affiliate\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliate\Models\Referral;
use App\Modules\Affiliate\Services\AffiliateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The owner's referral link, who joined through it, and the credit earned. */
class ReferralController extends Controller
{
    public function index(Request $request, AffiliateService $affiliate): View
    {
        abort_unless($affiliate->enabled(), 404);
        $restaurant = $request->user()->restaurant;

        return view('affiliate::index', [
            'restaurant' => $restaurant, 'link' => $affiliate->link($restaurant), 'percent' => $affiliate->percent(), 'currency' => $affiliate->currency(),
            'referrals' => Referral::with('referred')->where('referrer_id', $restaurant->id)->latest('id')->get(),
        ]);
    }
}
