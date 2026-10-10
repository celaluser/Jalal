<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\Services\PwaIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Lets waiters and kitchen staff put the staff screens on their phone's home screen like an app. */
class StaffAppController extends Controller
{
    public function manifest(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant, 404);

        return response()->json([
            'name' => $restaurant->name.' · '.__('orders.staff_app'), 'short_name' => mb_substr($restaurant->name, 0, 12),
            'start_url' => route('orders.board'), 'scope' => url('/'), 'display' => 'standalone', 'orientation' => 'any',
            'background_color' => '#0f1115', 'theme_color' => $restaurant->brandColor(),
            'icons' => collect(PwaIcon::SIZES)->map(fn ($s) => ['src' => route('staff.icon', $s), 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any maskable'])->all(),
            'shortcuts' => [
                ['name' => __('panel.nav.orders'), 'url' => route('orders.board')],
                ['name' => __('orders.kds_title'), 'url' => route('orders.kds')],
                ['name' => __('tables.map_title'), 'url' => route('tables.map')],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function icon(Request $request, int $size, PwaIcon $icons): Response
    {
        abort_unless(in_array($size, PwaIcon::SIZES, true) && $request->user()->restaurant, 404);

        return response($icons->png($request->user()->restaurant, $size), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }
}
