<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PrintJob;
use App\Modules\Orders\Services\TicketPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The printer bridge's side (token in the address, no login): hand out the next ticket, and hear back when it printed.
 * Plus the staff side: queue a ticket by hand and renew the bridge token.
 */
class PrintQueueController extends Controller
{
    /** A ticket handed out but never confirmed is offered again after this many seconds, up to three tries. */
    private const RETRY_AFTER = 45;

    public function __construct(private readonly TicketPrinter $printer, private readonly TenantContext $tenant) {}

    public function next(Request $request): JsonResponse|Response
    {
        $restaurant = $this->printer->restaurantFor((string) $request->route('token'));
        abort_unless($restaurant, 404);

        $job = $this->tenant->runAs($restaurant, function () {
            $job = PrintJob::where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($q) => $q->where('status', 'sent')->where('sent_at', '<', now()->subSeconds(self::RETRY_AFTER))->where('attempts', '<', 3)))
                ->orderBy('id')->first();

            $job?->forceFill(['status' => 'sent', 'sent_at' => now(), 'attempts' => $job->attempts + 1])->save();

            return $job;
        });

        return $job ? response()->json(['id' => $job->id, 'kind' => $job->kind, 'payload' => $job->payload])->header('Cache-Control', 'no-store') : response()->noContent();
    }

    public function done(Request $request): JsonResponse
    {
        $restaurant = $this->printer->restaurantFor((string) $request->route('token'));
        abort_unless($restaurant, 404);
        $ok = $request->boolean('ok', true);

        $this->tenant->runAs($restaurant, fn () => PrintJob::where('id', (int) $request->route('job'))->update(['status' => $ok ? 'done' : 'failed']));

        return response()->json(['ok' => true]);
    }

    /** "Print" on an order: kitchen ticket or receipt. */
    public function queue(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->canAny(['orders.view', 'kitchen.view']), 403);
        $kind = $request->validate(['kind' => ['required', Rule::in(['kitchen', 'receipt'])]])['kind'];
        $this->printer->enqueue($order, $kind);

        return back()->with('status', __('orders.print_queued'));
    }

    public function renewToken(Request $request): RedirectResponse
    {
        $this->printer->token($request->user()->restaurant, true);

        return back()->with('status', __('orders.print_token_renewed'));
    }
}
