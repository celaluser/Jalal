<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\PushSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushOptInController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'], 'keys.p256dh' => ['required', 'string', 'max:200'], 'keys.auth' => ['required', 'string', 'max:100'],
        ]);

        PushSubscriber::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            ['endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'], 'locale' => app()->getLocale()],
        );

        return response()->json(['ok' => true]);
    }
}
