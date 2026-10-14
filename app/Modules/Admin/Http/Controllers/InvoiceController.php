<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\PaymentProcessor;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices, private readonly PaymentProcessor $processor) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('admin::invoices.index', [
            'invoices' => Invoice::allTenants()
                ->when(in_array($status, ['open', 'paid', 'void', 'refunded'], true), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
            'restaurants' => Restaurant::withTrashed()->pluck('name', 'id'),
            'status' => $status,
        ]);
    }

    public function pdf(int $invoice)
    {
        $model = Invoice::allTenants()->findOrFail($invoice);

        return $this->invoices->pdf($model)->download($model->number.'.pdf');
    }

    public function markPaid(int $invoice): RedirectResponse
    {
        // settle() also activates the plan the invoice is for (e.g. a confirmed bank transfer).
        $this->processor->settle(Invoice::allTenants()->findOrFail($invoice), 'manual');

        return back()->with('status', __('admin.invoices.marked_paid'));
    }

    public function void(int $invoice): RedirectResponse
    {
        $model = Invoice::allTenants()->findOrFail($invoice);
        abort_if($model->status === 'paid', 422, __('admin.invoices.cannot_void_paid'));

        $this->invoices->void($model);

        return back()->with('status', __('admin.invoices.voided'));
    }
}
