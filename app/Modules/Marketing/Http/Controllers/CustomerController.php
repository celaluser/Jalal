<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The restaurant's guests: who orders, how often, and who agreed to hear from the restaurant. */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): View
    {
        $filter = $request->query('filter');
        $sort = in_array($request->query('sort'), ['recent', 'orders', 'spent', 'name'], true) ? $request->query('sort') : 'recent';

        $query = $this->query($request);
        $customers = (clone $query)->orderBy(match ($sort) {
            'orders' => 'orders_count', 'spent' => 'total_cents', 'name' => 'name', default => 'last_order_at',
        }, $sort === 'name' ? 'asc' : 'desc')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('marketing::customers.index', [
            'customers' => $customers, 'filter' => $filter, 'sort' => $sort, 'q' => trim((string) $request->query('q')),
            'totals' => ['all' => Customer::count(), 'opted' => Customer::where('marketing_opt_in', true)->whereNull('unsubscribed_at')->whereNotNull('email')->count(), 'repeat' => Customer::where('orders_count', '>=', 2)->count()],
            'canManage' => $request->user()->can('customers.manage'), 'restaurant' => $request->user()->restaurant,
        ]);
    }

    public function show(Request $request, int $customer): View
    {
        $model = Customer::findOrFail($customer);

        return view('marketing::customers.show', ['customer' => $model, 'orders' => $model->orders()->limit(30)->get(), 'canManage' => $request->user()->can('customers.manage'), 'restaurant' => $request->user()->restaurant]);
    }

    public function update(Request $request, int $customer): RedirectResponse
    {
        $model = Customer::findOrFail($customer);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $model->update(['notes' => $data['notes'] ?? null]);

        return back()->with('status', __('admin.saved'));
    }

    /** Erasure on request: removes the record and wipes the personal details from their orders. */
    public function destroy(int $customer): RedirectResponse
    {
        $this->customers->forget(Customer::findOrFail($customer));

        return redirect()->route('customers.index')->with('status', __('marketing.customer_deleted'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8
            fputcsv($out, ['Name', 'E-mail', 'Phone', 'Orders', 'Total spent', 'First order', 'Last order', 'Marketing consent', 'Notes']);

            $query->chunkById(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, array_map([$this, 'safe'], [
                        $c->name, $c->email, $c->phone, $c->orders_count, number_format($c->total_cents / 100, 2, '.', ''),
                        $c->first_order_at?->toDateString(), $c->last_order_at?->toDateString(), $c->canBeEmailed() ? 'yes' : 'no', $c->notes,
                    ]));
                }
            });
            fclose($out);
        }, 'customers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A cell that starts with = + - @ would run as a formula when the file is opened in a spreadsheet. */
    private function safe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    private function query(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        return Customer::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)))
            ->when($request->query('filter') === 'opted', fn ($query) => $query->where('marketing_opt_in', true)->whereNull('unsubscribed_at')->whereNotNull('email'))
            ->when($request->query('filter') === 'repeat', fn ($query) => $query->where('orders_count', '>=', 2));
    }
}
