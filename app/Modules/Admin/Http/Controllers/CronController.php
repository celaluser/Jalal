<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\WebCron;
use Illuminate\Http\JsonResponse;

/** GET /cron/{token}: what an external cron service calls every minute. A wrong token looks like a missing page. */
class CronController extends Controller
{
    public function __invoke(WebCron $cron, string $token): JsonResponse
    {
        abort_unless($cron->valid($token), 404);

        return response()->json($cron->run())->header('Cache-Control', 'no-store');
    }
}
