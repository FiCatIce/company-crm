<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesModelAbilities;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    use AuthorizesRequests, ProvidesModelAbilities;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $search = trim((string) $request->input('search', ''));

        $transactions = Transaction::query()
            ->with(['customer:id,name', 'product:id,name,warranty_months', 'reseller:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                // Case-insensitive across drivers (ILIKE on Postgres, lower() on SQLite);
                // escape LIKE wildcards so a user's % or _ can't broaden the match.
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->whereHas('customer', fn ($q) => $q->whereLike('name', $term, caseSensitive: false))
                    ->orWhereHas('product', fn ($q) => $q->whereLike('name', $term, caseSensitive: false));
            })
            ->latest('purchased_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'customer' => $transaction->customer?->name,
                'product' => $transaction->product?->name,
                'reseller' => $transaction->reseller?->name,
                'purchased_at' => $transaction->purchased_at->toDateString(),
                'warranty_months' => $transaction->product?->warranty_months,
                'warranty_expires_at' => $transaction->warranty_expires_at->toDateString(),
                'is_under_warranty' => $transaction->is_under_warranty,
            ]);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => ['search' => $search],
            'can' => $this->abilities($request, new Transaction),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Create', $this->formOptions());
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        Transaction::create($request->validated());

        return redirect()->route('transactions.index')
            ->with('success', 'Transaksi berhasil ditambahkan.');
    }

    public function edit(Request $request, Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        return Inertia::render('Transactions/Edit', [
            'transaction' => [
                'id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'product_id' => $transaction->product_id,
                'reseller_id' => $transaction->reseller_id,
                'purchased_at' => $transaction->purchased_at->toDateString(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $transaction->update($request->validated());

        return redirect()->route('transactions.index')
            ->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return redirect()->route('transactions.index')
            ->with('success', 'Transaksi berhasil dihapus.');
    }

    /**
     * Select options shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
        ];
    }
}
