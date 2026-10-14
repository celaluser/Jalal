<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Receives the new order after a drag-and-drop: {"ids": [3, 1, 2]}. */
class ReorderController extends Controller
{
    public function __invoke(Request $request, string $type, MenuService $menu): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);
        $menu->reorder($type, array_map('intval', $data['ids']));

        return response()->json(['ok' => true]);
    }
}
