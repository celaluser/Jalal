<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\RecipeLine;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Mail\PurchaseOrderMail;
use App\Modules\Inventory\Services\PurchaseOrders;
use App\Modules\Inventory\Services\Recipes;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Raw materials, deliveries, suppliers and recipes. */
class InventoryController extends Controller
{
    public function __construct(private readonly Recipes $recipes) {}

    public function index(Request $request): View
    {
        $ingredients = Ingredient::orderBy('name')->get();

        return view('inventory::index', [
            'restaurant' => $request->user()->restaurant,
            'ingredients' => $ingredients,
            'suppliers' => Supplier::orderBy('name')->get(),
            'purchases' => Purchase::latest('purchased_on')->latest('id')->limit(15)->get(),
            'names' => $ingredients->pluck('name', 'id'),
            'supplierNames' => Supplier::pluck('name', 'id'),
            'low' => $ingredients->filter->isLow()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        abort_if(Ingredient::count() >= 2000, 422);
        $data['stock_qty'] = (float) ($data['stock_qty'] ?? 0);
        Ingredient::create($data);

        return back()->with('status', __('inventory.saved'));
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $this->validated($request) + ['stock_qty' => (float) $request->input('stock_qty', $ingredient->stock_qty)];

        // Topping the stock up above the warning level re-arms the low-stock e-mail.
        if (isset($data['low_at']) && $data['stock_qty'] > (float) $data['low_at']) {
            $data['low_notified_at'] = null;
        }

        $ingredient->update($data);
        $this->recipes->recost(RecipeLine::where('ingredient_id', $ingredient->id)->pluck('product_id')->all());

        return back()->with('status', __('inventory.saved'));
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        RecipeLine::where('ingredient_id', $ingredient->id)->delete();
        $ingredient->delete();

        return back()->with('status', __('inventory.deleted'));
    }

    public function purchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ingredient_id' => ['required', 'integer'], 'qty' => ['required', 'numeric', 'gt:0', 'max:9999999'], 'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'supplier_id' => ['nullable', 'integer'], 'note' => ['nullable', 'string', 'max:255'], 'purchased_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $supplier = ! empty($data['supplier_id']) ? Supplier::findOrFail($data['supplier_id'])->id : null;
        $this->recipes->purchase($ingredient, (float) $data['qty'], (float) $data['unit_cost'], $supplier, $data['note'] ?? null, $data['purchased_on'] ?? null);

        return back()->with('status', __('inventory.purchase_saved'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'], 'note' => ['nullable', 'string', 'max:255']]);
        abort_if(Supplier::count() >= 500, 422);
        Supplier::create($data);

        return back()->with('status', __('inventory.saved'));
    }

    public function destroySupplier(Supplier $supplier): RedirectResponse
    {
        Ingredient::where('supplier_id', $supplier->id)->update(['supplier_id' => null]);
        $supplier->delete();

        return back()->with('status', __('inventory.deleted'));
    }

    /** What is running low, grouped by supplier, with quantities to order. */
    public function orders(Request $request): View
    {
        return view('inventory::orders', ['restaurant' => $request->user()->restaurant, 'groups' => app(PurchaseOrders::class)->groups()]);
    }

    public function orderPdf(Request $request): Response
    {
        [$supplier, $lines, $number, $note] = $this->prepareOrder($request);

        return Pdf::loadView('inventory::po-pdf', $this->pdfData($request, $supplier, $lines, $number, $note))->download('purchase-order-'.$number.'.pdf');
    }

    public function orderMail(Request $request): RedirectResponse
    {
        [$supplier, $lines, $number, $note] = $this->prepareOrder($request);

        if (! $supplier || ! $supplier->email) {
            return back()->withErrors(['supplier_id' => __('inventory.po_no_email')]);
        }

        $restaurant = $request->user()->restaurant;
        $pdf = Pdf::loadView('inventory::po-pdf', $this->pdfData($request, $supplier, $lines, $number, $note))->output();

        try {
            Mail::to($supplier->email)->send(new PurchaseOrderMail($restaurant, $supplier, $lines, $number, $pdf, $note));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['supplier_id' => __('inventory.po_send_failed')]);
        }

        return back()->with('status', __('inventory.po_sent', ['name' => $supplier->name]));
    }

    /** @return array{0: ?Supplier, 1: list<array<string, mixed>>, 2: string, 3: ?string} */
    private function prepareOrder(Request $request): array
    {
        $data = $request->validate(['supplier_id' => ['required', 'integer', 'min:0'], 'qty' => ['required', 'array', 'max:500'], 'qty.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'note' => ['nullable', 'string', 'max:500']]);
        $supplier = (int) $data['supplier_id'] > 0 ? Supplier::findOrFail((int) $data['supplier_id']) : null;
        $lines = app(PurchaseOrders::class)->lines((int) $data['supplier_id'], $data['qty']);
        abort_if($lines === [], 422);

        return [$supplier, $lines, 'PO-'.now()->format('ymd-Hi'), $data['note'] ?? null];
    }

    /** @param list<array<string, mixed>> $lines @return array<string, mixed> */
    private function pdfData(Request $request, ?Supplier $supplier, array $lines, string $number, ?string $note): array
    {
        return ['restaurant' => $request->user()->restaurant, 'supplier' => $supplier, 'lines' => $lines, 'number' => $number, 'note' => $note, 'date' => now()->toDateString(), 'total' => round(collect($lines)->sum('est'), 2)];
    }

    public function recipes(Request $request): View
    {
        $lines = RecipeLine::with('ingredient')->get()->groupBy('product_id');

        return view('inventory::recipes', [
            'restaurant' => $request->user()->restaurant,
            'categories' => Category::with(['products' => fn ($q) => $q->orderBy('sort')->orderBy('id')])->orderBy('sort')->orderBy('id')->get(),
            'lines' => $lines,
            'costs' => $lines->map(fn ($l, $productId) => $this->recipes->cost((int) $productId)),
        ]);
    }

    public function editRecipe(Request $request, Product $product): View
    {
        return view('inventory::recipe', [
            'restaurant' => $request->user()->restaurant,
            'product' => $product,
            'ingredients' => Ingredient::orderBy('name')->get(),
            'qty' => RecipeLine::where('product_id', $product->id)->pluck('qty', 'ingredient_id'),
            'cost' => $this->recipes->cost($product->id),
        ]);
    }

    public function updateRecipe(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['qty' => ['nullable', 'array', 'max:200'], 'qty.*' => ['nullable', 'numeric', 'min:0', 'max:9999999']]);
        $this->recipes->save($product, array_filter($data['qty'] ?? [], fn ($v) => $v !== null && $v !== ''));

        return back()->with('status', __('inventory.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'unit' => ['required', Rule::in(Ingredient::UNITS)],
            'stock_qty' => ['nullable', 'numeric', 'min:-9999999', 'max:9999999'], 'low_at' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('restaurant_id', $request->user()->restaurant_id)],
        ]);
    }
}
