<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesModelAbilities;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Reseller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    use AuthorizesRequests, ProvidesModelAbilities;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $search = trim((string) $request->input('search', ''));
        $resellerId = $request->integer('reseller') ?: null;

        $customers = Customer::query()
            ->with('reseller:id,name')
            ->when($search !== '', function ($query) use ($search) {
                // Case-insensitive across drivers (ILIKE on Postgres, lower() on SQLite);
                // escape LIKE wildcards so a user's % or _ can't broaden the match.
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($query) use ($term) {
                    $query->whereLike('name', $term, caseSensitive: false)
                        ->orWhereLike('email', $term, caseSensitive: false)
                        ->orWhereLike('phone', $term, caseSensitive: false);
                });
            })
            ->when($resellerId, fn ($query, $id) => $query->where('reseller_id', $id))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'reseller' => $customer->reseller?->name,
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'reseller' => $resellerId,
            ],
            'can' => $this->abilities($request, new Customer),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Customers/Create', [
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil ditambahkan.');
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'reseller_id' => $customer->reseller_id,
            ],
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil diperbarui.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->transactions()->exists()) {
            return back()->with('error', 'Customer tidak dapat dihapus karena masih memiliki transaksi. Hapus atau pindahkan transaksinya terlebih dahulu.');
        }

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil dihapus.');
    }
}
