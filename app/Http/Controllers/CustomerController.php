<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Reseller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $search = trim((string) $request->input('search', ''));
        $resellerId = $request->integer('reseller') ?: null;

        $customers = Customer::query()
            ->with('reseller:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
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
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Customers/Create', [
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        Customer::create($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil ditambahkan.');
    }

    public function edit(Request $request, Customer $customer)
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

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil diperbarui.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil dihapus.');
    }

    /**
     * Role-based abilities surfaced to the UI (independent of a specific row).
     *
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();
        $customer = new Customer;

        return [
            'create' => $user->can('create', Customer::class),
            'update' => $user->can('update', $customer),
            'delete' => $user->can('delete', $customer),
        ];
    }
}
