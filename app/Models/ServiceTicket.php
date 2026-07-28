<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\TicketCoverage;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\ServiceTicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A repair job on a customer's unit (DESIGN_MAINTENANCE.md). Deliberately
 * money-free (D4): a paid repair is recorded as a Transaction elsewhere — this
 * row only says whether the work falls under warranty.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $transaction_id
 * @property int|null $product_id
 * @property string|null $item_description
 * @property string|null $serial_no
 * @property string $complaint
 * @property string|null $work_notes
 * @property string|null $resolution
 * @property TicketCoverage $coverage
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property Carbon $received_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ServiceTicket extends Model
{
    /** @use HasFactory<ServiceTicketFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'transaction_id',
        'product_id',
        'item_description',
        'serial_no',
        'complaint',
        'work_notes',
        'resolution',
        'coverage',
        'priority',
        'received_at',
        // status / assigned_to / created_by / lifecycle stamps are deliberately
        // NOT mass-assignable: they move through explicit transitions (M2).
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'transaction_id' => 'integer',
        'product_id' => 'integer',
        'assigned_to' => 'integer',
        'created_by' => 'integer',
        'coverage' => TicketCoverage::class,
        'priority' => TicketPriority::class,
        'status' => TicketStatus::class,
        'received_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Row visibility (DESIGN_MAINTENANCE.md §2.2, decision D3): delegate to the
     * customer-visibility axis (L1), then union in the viewer's OWN work —
     * assigned-to-me and created-by-me. Being assigned a ticket shows THAT
     * ticket, never the customer behind it: the customer axis does not widen.
     *
     * Dormant while no preset holds ticket.view (M1): everyone resolves to zero
     * rows, which is exactly the no-op this batch promises.
     *
     * @param  Builder<ServiceTicket>  $query
     * @return Builder<ServiceTicket>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can(PermissionName::TicketView->value)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($user): void {
            // Subquery (not whereHas) keeps the customer-visibility logic in one
            // place — the same shape Interaction::scopeVisibleTo uses.
            $scope->whereIn('customer_id', Customer::visibleTo($user)->select('id'))
                ->orWhere('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }
}
