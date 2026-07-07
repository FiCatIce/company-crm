<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $search = trim((string) $request->input('search', ''));

        $products = Product::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
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
            'filters' => ['search' => $search],
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Create');
    }

    public function store(StoreProductRequest $request)
    {
        Product::create($request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Request $request, Product $product)
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

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * Role-based abilities surfaced to the UI (independent of a specific row).
     *
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();
        $product = new Product;

        return [
            'create' => $user->can('create', Product::class),
            'update' => $user->can('update', $product),
            'delete' => $user->can('delete', $product),
        ];
    }
}
