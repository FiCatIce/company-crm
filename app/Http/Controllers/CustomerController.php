<?php

namespace App\Http\Controllers;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InteractionDirection;
use App\Enums\InteractionOutcome;
use App\Enums\InteractionType;
use App\Http\Controllers\Concerns\ProvidesModelAbilities;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $status = CustomerStatus::tryFrom((string) $request->input('status', ''));
        // Owner scope: 'me' (mine), 'unassigned' (none), or a specific user id.
        $ownerRaw = (string) $request->input('owner', '');
        $owner = match (true) {
            $ownerRaw === 'me', $ownerRaw === 'unassigned' => $ownerRaw,
            ctype_digit($ownerRaw) => $ownerRaw,
            default => null,
        };
        $authId = (int) $request->user()->id;

        $customers = Customer::query()
            ->with(['reseller:id,name', 'owner:id,name'])
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
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($owner === 'me', fn ($query) => $query->where('assigned_to', $authId))
            ->when($owner === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->when($owner !== null && ctype_digit($owner), fn ($query) => $query->where('assigned_to', (int) $owner))
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
                'status' => $customer->status->value,
                'status_label' => $customer->status->label(),
                'owner' => $customer->owner
                    ? ['id' => $customer->owner->id, 'name' => $customer->owner->name]
                    : null,
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
            'users' => $this->userOptions(),
            'stats' => $this->stats(),
            'filters' => [
                'search' => $search,
                'reseller' => $resellerId,
                'status' => $status?->value,
                'owner' => $owner,
            ],
            'can' => $this->abilities($request, new Customer),
        ]);
    }

    /**
     * Summary metrics for the index header cards (whole dataset, ignores filters).
     *
     * @return array{total: int, underWarranty: int, resellers: int}
     */
    private function stats(): array
    {
        // "Under warranty" relies on the Transaction accessor (not a DB column),
        // so resolve it in PHP over the transactions and count distinct customers.
        $customersUnderWarranty = Transaction::query()
            ->with('product:id,warranty_months')
            ->get(['id', 'customer_id', 'product_id', 'purchased_at'])
            ->filter(fn (Transaction $transaction) => $transaction->is_under_warranty)
            ->pluck('customer_id')
            ->unique()
            ->count();

        return [
            'total' => Customer::count(),
            'underWarranty' => $customersUnderWarranty,
            'resellers' => Reseller::count(),
        ];
    }

    /**
     * Lifecycle status options for the filter/quick-change selects.
     *
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (CustomerStatus $status) => ['value' => $status->value, 'label' => $status->label()],
            CustomerStatus::cases(),
        );
    }

    /**
     * Lead-source options for the create/edit form.
     *
     * @return list<array{value: string, label: string}>
     */
    private function sourceOptions(): array
    {
        return array_map(
            fn (CustomerSource $source) => ['value' => $source->value, 'label' => $source->label()],
            CustomerSource::cases(),
        );
    }

    /**
     * Staff options (value = user id as string) for the owner assign/filter selects.
     *
     * @return list<array{value: string, label: string}>
     */
    private function userOptions(): array
    {
        $users = User::orderBy('name')->get(['id', 'name'])->all();

        return array_map(
            fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name],
            array_values($users),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Customers/Create', [
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
            'sources' => $this->sourceOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil ditambahkan.');
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $user = $request->user();

        $customer->load(['reseller:id,name', 'owner:id,name']);

        // Fetched once and reused for both the list and the warranty summary.
        $transactions = $customer->transactions()
            ->with('product:id,name,warranty_months')
            ->latest('purchased_at')
            ->latest('id')
            ->get();

        $warrantySummary = ['active' => 0, 'expired' => 0, 'none' => 0];

        foreach ($transactions as $transaction) {
            if ($transaction->product && $transaction->product->warranty_months > 0) {
                $transaction->is_under_warranty ? $warrantySummary['active']++ : $warrantySummary['expired']++;
            } else {
                $warrantySummary['none']++;
            }
        }

        $timeline = $customer->interactions()
            ->with('user:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20)
            ->through(fn (Interaction $interaction) => [
                'id' => $interaction->id,
                'type' => $interaction->type->value,
                'type_label' => $interaction->type->label(),
                'direction' => $interaction->direction?->value,
                'subject' => $interaction->subject,
                'body' => $interaction->body,
                'outcome' => $interaction->outcome?->value,
                'outcome_label' => $interaction->outcome?->label(),
                'duration_sec' => $interaction->duration_sec,
                'occurred_at' => $interaction->occurred_at->toIso8601String(),
                'source' => $interaction->source->value,
                'user' => $interaction->user
                    ? ['id' => $interaction->user->id, 'name' => $interaction->user->name]
                    : null,
                // Per-row (not the row-independent ProvidesModelAbilities): CTI/import
                // logs are immutable, so editability varies by interaction.
                'can_edit' => $user->can('update', $interaction),
                'can_delete' => $user->can('delete', $interaction),
            ]);

        $lastContacted = $customer->interactions()
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'status' => $customer->status->value,
                'status_label' => $customer->status->label(),
                'source' => $customer->source?->value,
                'source_label' => $customer->source?->label(),
                'reseller' => $customer->reseller
                    ? ['id' => $customer->reseller->id, 'name' => $customer->reseller->name]
                    : null,
                'owner' => $customer->owner
                    ? ['id' => $customer->owner->id, 'name' => $customer->owner->name]
                    : null,
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
            'timeline' => $timeline,
            'transactions' => $transactions->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'product' => $transaction->product?->name,
                'purchased_at' => $transaction->purchased_at->toDateString(),
                'warranty_months' => $transaction->product?->warranty_months,
                'warranty_expires_at' => $transaction->warranty_expires_at->toDateString(),
                'is_under_warranty' => $transaction->is_under_warranty,
                'amount' => $transaction->amount,
            ])->all(),
            'warrantySummary' => $warrantySummary,
            'stats' => [
                'interactionsCount' => $timeline->total(),
                'lastContactedAt' => $lastContacted?->occurred_at->toIso8601String(),
                'transactionsCount' => $transactions->count(),
                'totalSpend' => (float) $transactions->sum(fn (Transaction $transaction) => (float) $transaction->amount),
            ],
            'statuses' => $this->statusOptions(),
            'users' => $this->userOptions(),
            'can' => [
                'update' => $user->can('update', $customer),
                'delete' => $user->can('delete', $customer),
                'logInteraction' => $user->can('create', Interaction::class),
            ],
            'interactionOptions' => [
                'types' => array_map(fn (InteractionType $t) => ['value' => $t->value, 'label' => $t->label()], InteractionType::cases()),
                'directions' => array_map(fn (InteractionDirection $d) => ['value' => $d->value, 'label' => $d->label()], InteractionDirection::cases()),
                'outcomes' => array_map(fn (InteractionOutcome $o) => ['value' => $o->value, 'label' => $o->label()], InteractionOutcome::cases()),
            ],
        ]);
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
                'assigned_to' => $customer->assigned_to,
                'status' => $customer->status->value,
                'source' => $customer->source?->value,
            ],
            'resellers' => Reseller::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
            'sources' => $this->sourceOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil diperbarui.');
    }

    /**
     * Quick lifecycle-status change from the Customer 360 header (status only).
     */
    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(CustomerStatus::class)],
        ]);

        $customer->update($validated);

        return back()->with('success', 'Status customer diperbarui.');
    }

    /**
     * Quick owner reassignment from the Customer 360 header (assigned_to only).
     * Assignment is attribution/filtering — it never gates access.
     */
    public function updateOwner(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $customer->update(['assigned_to' => $validated['assigned_to'] ?? null]);

        return back()->with('success', 'Owner customer diperbarui.');
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
