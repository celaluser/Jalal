<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Storefront\Services\MenuLocale;
use App\Modules\Tables\Qr\QrCode;
use App\Modules\Tables\Qr\QrStyle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** The digital receipt: a page the guest can keep, print or save as PDF, with a QR code that leads back to it. */
class ReceiptController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly MenuLocale $locales) {}

    public function show(Request $request): View
    {
        return view('orders::customer.receipt', $this->data($request, false) + ['pdf' => false]);
    }

    public function pdf(Request $request): Response
    {
        $data = $this->data($request, true);

        return Pdf::loadView('orders::customer.receipt', $data + ['pdf' => true])->setPaper([0, 0, 226.8, 700])->download('receipt-'.$data['order']->number.'.pdf');
    }

    /** @return array<string, mixed> */
    private function data(Request $request, bool $png): array
    {
        $restaurant = $this->tenant->get();
        app()->setLocale($this->locales->resolve($request, $restaurant));
        $order = Order::with(['items', 'payments'])->where('token', (string) $request->route('token'))->firstOrFail();
        $url = $restaurant->publicUrl('order/'.$order->token.'/receipt');
        $style = new QrStyle('#000000', '#ffffff', 'square');

        return [
            'restaurant' => $restaurant, 'order' => $order, 'money' => fn (int $c) => $restaurant->money($c / 100),
            'qr' => $png ? 'data:image/png;base64,'.base64_encode(QrCode::png($url, $style, 300)) : QrCode::svg($url, $style),
            'url' => $url, 'tz' => in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC',
        ];
    }
}
