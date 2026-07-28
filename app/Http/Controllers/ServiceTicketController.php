<?php

namespace App\Http\Controllers;

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

        $query = ServiceTicket::query()->visibleTo($user)
            ->with(['customer:id,name', 'product:id,name', 'transaction:id,product_id', 'transaction.product:id,name', 'assignee:id,name'])
            ->latest('received_at');

        if (($status = $request->string('status')->toString()) !== '' && TicketStatus::tryFrom($status) !== null) {
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

        if (($search = $request->string('search')->toString()) !== '') {
            $query->whereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
        }

        $stats = [
            'active' => ServiceTicket::query()->visibleTo($user)
                ->whereIn('status', [TicketStatus::Received, TicketStatus::InProgress])->count(),
            'doneThisMonth' => ServiceTicket::query()->visibleTo($user)
                ->where('status', TicketStatus::Done)
                ->where('finished_at', '>=', now()->startOfMonth())->count(),
        ];

        return Inertia::render('ServiceTickets/Index', [
            'tickets' => $query->paginate(10)->withQueryString()
                ->through(fn (ServiceTicket $ticket) => $this->ticketRow($ticket)),
            'stats' => $stats,
            'filters' => $request->only(['status', 'priority', 'assigned', 'search']),
            'can' => ['create' => $user->can('create', ServiceTicket::class)],
        ]);
    }

    public function show(Request $request, ServiceTicket $serviceTicket): Response
    {
        $this->authorize('view', $serviceTicket);

        $serviceTicket->load([
            'customer:id,name,phone', 'product:id,name,warranty_months',
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
