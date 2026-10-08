<?php

namespace App\Modules\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Models\Branch;
use App\Modules\Branches\Models\BranchProduct;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Per-branch price, sold-out switch and portions for every dish. A blank cell means "same as the main menu". */
class BranchMenuController extends Controller
{
    public function edit(Branch $branch): View
    {
        return view('branches::branches.menu', [
            'branch' => $branch,
            'categories' => Category::with(['products' => fn ($q) => $q->orderBy('sort')])->orderBy('sort')->get(),
            'rows' => BranchProduct::where('branch_id', $branch->id)->get()->keyBy('product_id'),
            'others' => Branch::where('id', '!=', $branch->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.available' => ['nullable', 'in:,1,0'],
            'items.*.stock_qty' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);
        $valid = Product::pluck('id')->all();

        foreach ($data['items'] ?? [] as $productId => $row) {
            if (! in_array((int) $productId, $valid, true)) {
                continue;
            }

            $values = [
                'price' => ($row['price'] ?? '') === '' ? null : $row['price'],
                'is_available' => ($row['available'] ?? '') === '' ? null : $row['available'] === '1',
                'stock_qty' => ($row['stock_qty'] ?? '') === '' ? null : (int) $row['stock_qty'],
            ];

            // All blank = no override at all.
            if (collect($values)->every(fn ($v) => $v === null)) {
                BranchProduct::where('branch_id', $branch->id)->where('product_id', $productId)->delete();

                continue;
            }

            BranchProduct::updateOrCreate(['branch_id' => $branch->id, 'product_id' => (int) $productId], $values);
        }

        return back()->with('status', __('admin.saved'));
    }

    /** Copies another branch's overrides onto this one (replacing what was here). */
    public function copy(Request $request, Branch $branch): RedirectResponse
    {
        $from = Branch::where('id', '!=', $branch->id)->findOrFail((int) $request->validate(['from' => ['required', 'integer']])['from']);

        BranchProduct::where('branch_id', $branch->id)->delete();

        foreach (BranchProduct::where('branch_id', $from->id)->get() as $row) {
            BranchProduct::create(['branch_id' => $branch->id, 'product_id' => $row->product_id, 'price' => $row->price, 'is_available' => $row->is_available, 'stock_qty' => $row->stock_qty]);
        }

        return back()->with('status', __('branches.copied', ['name' => $from->name]));
    }
}
