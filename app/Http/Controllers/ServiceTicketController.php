<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\TicketCoverage;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\AssignServiceTicketRequest;
use App\Http\Requests\StoreServiceTicketRequest;
use App\Http\Requests\UpdateServiceTicketRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Support\HierarchyResolver;
use App\Support\TicketFlow;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Service tickets (DESIGN_MAINTENANCE.md M2): scoped CRUD + the explicit status
 * transitions. Every list/record read runs through ServiceTicket::visibleTo;
 * every transition goes through TicketFlow (the only writer of status/stamps).
 */
class ServiceTicketController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServiceTicket::class);

        $user = $request->user();

        // customer carries created_by/assigned_to: the policy verdicts below
        // (claim / customer_viewable) resolve hierarchy sight from those columns —
        // trimming them made every verdict read null ownership and fail closed
        // (same eager-load trap as the /team is_active badge).
        $query = ServiceTicket::query()->visibleTo($user)
            ->with(['customer:id,name,created_by,assigned_to', 'product:id,name', 'transaction:id,product_id', 'transaction.product:id,name', 'assignee:id,name'])
            ->latest('received_at');

        $status = $request->string('status')->toString();

        if ($status === 'active') {
            // Virtual bucket (M4): the dashboard's "aktif" cards link here, so the
            // filter must count through the same definition the card used.
            $query->whereIn('status', TicketStatus::active());
        } elseif ($status !== '' && TicketStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        if (($priority = $request->string('priority')->toString()) !== '' && TicketPriority::tryFrom($priority) !== null) {
            $query->where('priority', $priority);
        }

        match ($request->string('assigned')->toString()) {
            'me' => $query->where('assigned_to', $user->id),
            'pool' => $query->whereNull('assigned_to'),
            default => null,
        };

        // Intake follow-up (M4): the CS dashboard card counts created-by-me tickets,
        // and its click-through lands here with the same bound.
        if ($request->string('created')->toString() === 'me') {
            $query->where('created_by', $user->id);
        }

        if (($search = $request->string('search')->toString()) !== '') {
            $query->whereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
        }

        $stats = [
            'active' => ServiceTicket::query()->visibleTo($user)
                ->whereIn('status', TicketStatus::active())->count(),
            'doneThisMonth' => ServiceTicket::query()->visibleTo($user)
                ->where('status', TicketStatus::Done)
                ->where('finished_at', '>=', now()->startOfMonth())->count(),
        ];

        return Inertia::render('ServiceTickets/Index', [
            'tickets' => $query->paginate(10)->withQueryString()
                ->through(fn (ServiceTicket $ticket) => $this->ticketRow($ticket) + [
                    // Per-row verdicts (same idiom as Customers/Index): claim is
                    // row-scoped policy; the customer link renders only when the
                    // viewer can actually open the 360 behind it (D3).
                    'can_claim' => $user->can('claim', $ticket),
                    'customer_viewable' => $ticket->customer !== null
                        && $user->can('view', $ticket->customer),
                ]),
            'stats' => $stats,
            'filters' => $request->only(['status', 'priority', 'assigned', 'created', 'search']),
            'statusOptions' => $this->enumOptions(TicketStatus::cases()),
            'priorityOptions' => $this->enumOptions(TicketPriority::cases()),
            'can' => ['create' => $user->can('create', ServiceTicket::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ServiceTicket::class);

        $user = $request->user();

        // The intake form only ever offers what the CREATOR can reach (D3/§3.3):
        // scoped customers, and the picked customer's own purchases as anchors
        // (fetched via partial reload on ?customer_id — never another book's).
        $customers = Customer::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Customer $customer): array => [
                'value' => (string) $customer->id,
                'label' => $customer->name,
            ])->values()->all();

        $transactions = [];
        $customerId = (int) $request->query('customer_id');

        if ($customerId > 0) {
            $picked = Customer::query()->visibleTo($user)->whereKey($customerId)->first();

            $transactions = $picked?->transactions()->with('product:id,name,warranty_months')
                ->orderByDesc('purchased_at')->get()
                ->map(fn (Transaction $transaction): array => [
                    'value' => (string) $transaction->id,
                    'label' => $transaction->product->name.' · '.$transaction->purchased_at->translatedFormat('d M Y'),
                    'under_warranty' => $transaction->is_under_warranty,
                ])->values()->all() ?? [];
        }

        return Inertia::render('ServiceTickets/Create', [
            'customers' => $customers,
            'transactions' => $transactions,
            'pickedCustomerId' => $customerId > 0 ? (string) $customerId : '',
            'priorities' => $this->enumOptions(TicketPriority::cases()),
            'coverages' => $this->enumOptions(TicketCoverage::cases()),
        ]);
    }

    public function show(Request $request, ServiceTicket $serviceTicket): Response
    {
        $this->authorize('view', $serviceTicket);

        // created_by/assigned_to must ride along: the can.* verdicts resolve
        // hierarchy sight from the customer's ownership columns.
        $serviceTicket->load([
            'customer:id,name,phone,created_by,assigned_to', 'product:id,name,warranty_months',
            'transaction:id,product_id,purchased_at', 'transaction.product:id,name,warranty_months',
            'assignee:id,name', 'creator:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('ServiceTickets/Show', [
            'ticket' => $this->ticketRow($serviceTicket) + [
                'complaint' => $serviceTicket->complaint,
                'work_notes' => $serviceTicket->work_notes,
                'resolution' => $serviceTicket->resolution,
                'creator' => $serviceTicket->creator?->only(['id', 'name']),
                'started_at' => $serviceTicket->started_at,
                'finished_at' => $serviceTicket->finished_at,
                'cancelled_at' => $serviceTicket->cancelled_at,
            ],
            'can' => [
                'update' => $user->can('update', $serviceTicket),
                'claim' => $user->can('claim', $serviceTicket),
                'assign' => $user->can('assign', $serviceTicket),
            ],
            // Routing choices for a ticket.assign holder — bounded to THEIR team
            // (HierarchyResolver), so the picker never discloses another team's
            // roster. TicketFlow re-checks eligibility as the backstop.
            'assignableTechnicians' => $user->can('assign', $serviceTicket)
                ? $this->assignableTechnicians($user)
                : [],
        ]);
    }

    public function store(StoreServiceTicketRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Backstop for the request's scope rule (TOCTOU-safe): the customer must
        // still be reachable by the creator at write time.
        $customer = Customer::query()->visibleTo($user)->findOrFail((int) $data['customer_id']);

        $transaction = isset($data['transaction_id'])
            ? $customer->transactions()->with('product')->findOrFail((int) $data['transaction_id'])
            : null;

        $receivedAt = isset($data['received_at']) ? Date::parse($data['received_at']) : now();

        $ticket = new ServiceTicket([
            'customer_id' => $customer->id,
            'transaction_id' => $transaction?->id,
            'product_id' => $data['product_id'] ?? $transaction?->product_id,
            'item_description' => $data['item_description'] ?? null,
            'serial_no' => $data['serial_no'] ?? null,
            'complaint' => $data['complaint'],
            'coverage' => $data['coverage'] ?? $this->suggestCoverage($transaction, $receivedAt)->value,
            'priority' => $data['priority'] ?? TicketPriority::Normal->value,
            'received_at' => $receivedAt,
        ]);

        // Attribution is server-side only — never client input.
        $ticket->forceFill(['created_by' => $user->id])->save();

        return redirect()->route('service-tickets.show', $ticket)
            ->with('success', 'Tiket servis dibuat.');
    }

    public function update(UpdateServiceTicketRequest $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $serviceTicket->fill($request->validated())->save();

        return back()->with('success', 'Tiket diperbarui.');
    }

    public function destroy(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $this->authorize('delete', $serviceTicket);

        AuditLog::record($request->user(), null, 'ticket.deleted', [
            'ticket' => ['id' => $serviceTicket->id, 'customer_id' => $serviceTicket->customer_id],
        ]);

        $serviceTicket->delete();

        return redirect()->route('service-tickets.index')->with('success', 'Tiket dihapus.');
    }

    // --- Transitions (TicketFlow is the only status writer) ----------------------

    public function claim(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $this->authorize('claim', $serviceTicket);

        return $this->flowResult(
            TicketFlow::claim($request->user(), $serviceTicket),
            'Tiket diklaim — sekarang dipegang Anda.',
        );
    }

    public function start(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $this->authorize('update', $serviceTicket);

        return $this->flowResult(
            TicketFlow::start($request->user(), $serviceTicket),
            'Pengerjaan dimulai.',
        );
    }

    public function complete(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $this->authorize('update', $serviceTicket);

        $validated = $request->validate(
            ['resolution' => ['required', 'string', 'max:5000']],
            ['resolution.required' => 'Isi ringkasan penyelesaian sebelum menandai selesai.'],
        );

        return $this->flowResult(
            TicketFlow::complete($request->user(), $serviceTicket, $validated['resolution']),
            'Tiket selesai.',
        );
    }

    public function cancel(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $this->authorize('update', $serviceTicket);

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'Sebutkan alasan pembatalan.'],
        );

        return $this->flowResult(
            TicketFlow::cancel($request->user(), $serviceTicket, $validated['reason']),
            'Tiket dibatalkan.',
        );
    }

    public function assign(AssignServiceTicketRequest $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        return $this->flowResult(
            TicketFlow::assignTo($request->user(), $serviceTicket, $request->technician()),
            'Tiket ditugaskan.',
        );
    }

    private function flowResult(?string $error, string $success): RedirectResponse
    {
        return $error === null
            ? back()->with('success', $success)
            : back()->with('error', $error);
    }

    /**
     * Warranty call suggested from the transaction anchor (DESIGN_MAINTENANCE.md
     * §1.2): inside the purchased product's warranty window → warranty, outside →
     * paid, no anchor → unknown. A suggestion only — the request may override.
     */
    private function suggestCoverage(?Transaction $transaction, CarbonInterface $receivedAt): TicketCoverage
    {
        if ($transaction === null) {
            return TicketCoverage::Unknown;
        }

        // transactions.product_id is NOT NULL — the product is always there.
        $hasWarranty = $transaction->product->warranty_months > 0;

        return $hasWarranty && $receivedAt->lte($transaction->warranty_expires_at->endOfDay())
            ? TicketCoverage::Warranty
            : TicketCoverage::Paid;
    }

    /**
     * @param  list<TicketStatus|TicketCoverage|TicketPriority>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn ($case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }

    /**
     * Active team members who can WORK a ticket (hold ticket.update) — the
     * supervisor's routing pool, never the whole org.
     *
     * @return list<array{value: string, label: string}>
     */
    private function assignableTechnicians(User $user): array
    {
        $memberIds = HierarchyResolver::teamMemberIds($user);

        return array_values(User::query()->whereIn('id', $memberIds)
            ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->filter(fn (User $member): bool => $member->can(PermissionName::TicketUpdate->value))
            ->map(fn (User $member): array => [
                'value' => (string) $member->id,
                'label' => $member->name,
            ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketRow(ServiceTicket $ticket): array
    {
        // The unit label: catalog product if linked (directly or via the
        // transaction anchor), else the free-text description.
        $product = $ticket->product ?? $ticket->transaction?->product;

        return [
            'id' => $ticket->id,
            'customer' => $ticket->customer?->only(['id', 'name']),
            'item' => $product !== null ? $product->name : $ticket->item_description,
            'serial_no' => $ticket->serial_no,
            'status' => ['value' => $ticket->status->value, 'label' => $ticket->status->label()],
            'coverage' => ['value' => $ticket->coverage->value, 'label' => $ticket->coverage->label()],
            'priority' => ['value' => $ticket->priority->value, 'label' => $ticket->priority->label()],
            'assignee' => $ticket->assignee?->only(['id', 'name']),
            'received_at' => $ticket->received_at,
        ];
    }
}
