<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
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
        $canSeeAmount = $this->canSeeAmount($request->user());

        $transactions = Transaction::query()
            // Row-level scope first, so search operates strictly within what this
            // user may see (Sales → only their own customers' transactions).
            ->visibleTo($request->user())
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
            ->through(function (Transaction $transaction) use ($canSeeAmount) {
                $row = [
                    'id' => $transaction->id,
                    'customer' => $transaction->customer?->name,
                    'product' => $transaction->product?->name,
                    'reseller' => $transaction->reseller?->name,
                    'purchased_at' => $transaction->purchased_at->toDateString(),
                    'warranty_months' => $transaction->product?->warranty_months,
                    'warranty_expires_at' => $transaction->warranty_expires_at->toDateString(),
                    'is_under_warranty' => $transaction->is_under_warranty,
                ];

                // OMIT amount entirely (never send null) when the viewer lacks a
                // money permission — see DESIGN_RBAC.md §4.3.
                if ($canSeeAmount) {
                    $row['amount'] = $transaction->amount;
                }

                return $row;
            });

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'stats' => $this->stats($request),
            'filters' => ['search' => $search],
            'can' => $this->abilities($request, new Transaction),
        ]);
    }

    /**
     * Summary metrics for the index header cards (scoped to what the user sees,
     * ignores filters).
     *
     * @return array{total: int, underWarranty: int, expired: int}
     */
    private function stats(Request $request): array
    {
        // Warranty state comes from the model accessor, so resolve it in PHP.
        // "Expired" counts only transactions whose product carried a warranty
        // that has since ended — products sold without warranty are neither.
        $transactions = Transaction::query()
            ->visibleTo($request->user())
            ->with('product:id,warranty_months')
            ->get(['id', 'product_id', 'purchased_at']);

        return [
            'total' => $transactions->count(),
            'underWarranty' => $transactions
                ->filter(fn (Transaction $transaction) => $transaction->is_under_warranty)
                ->count(),
            'expired' => $transactions
                ->filter(fn (Transaction $transaction) => $transaction->product->warranty_months > 0
                    && ! $transaction->is_under_warranty)
                ->count(),
        ];
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Create', $this->formOptions($request));
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $transaction = Transaction::create($request->validated());

        // Lifecycle: a lead's first purchase promotes them to an active customer.
        $customer = $transaction->customer;
        if ($customer && $customer->status === CustomerStatus::Lead) {
            $customer->update(['status' => CustomerStatus::Active]);
        }

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
                'amount' => $transaction->amount,
            ],
            ...$this->formOptions($request),
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
     * Select options shared by the create and edit forms. The customer list is
     * scoped so a Sales user can only pick (and thus transact for) their own
     * customers; products and resellers are shared reference data.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'customers' => Customer::visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
        ];
    }
}
