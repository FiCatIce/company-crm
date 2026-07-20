<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesModelAbilities;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use AuthorizesRequests, ProvidesModelAbilities;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = trim((string) $request->input('search', ''));

        $products = Product::query()
            ->when($search !== '', fn ($query) => $query->whereLike('name', '%'.addcslashes($search, '%_\\').'%', caseSensitive: false))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'warranty_months' => $product->warranty_months,
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'stats' => $this->stats(),
            'filters' => ['search' => $search],
            'can' => $this->abilities($request, Product::class),
        ]);
    }

    /**
     * Summary metrics for the index header cards (whole dataset, ignores filters).
     *
     * @return array{total: int, withWarranty: int, avgWarrantyMonths: int}
     */
    private function stats(): array
    {
        return [
            'total' => Product::count(),
            'withWarranty' => Product::where('warranty_months', '>', 0)->count(),
            'avgWarrantyMonths' => (int) round((float) Product::avg('warranty_months')),
        ];
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'warranty_months' => $product->warranty_months,
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->transactions()->exists()) {
            return back()->with('error', 'Produk tidak dapat dihapus karena masih memiliki transaksi. Hapus transaksinya terlebih dahulu.');
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}
