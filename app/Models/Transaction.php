<?php

namespace App\Models;

use App\Enums\PermissionName;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $amount
 * @property-read Carbon $warranty_expires_at
 * @property-read bool $is_under_warranty
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'product_id', 'purchased_at', 'amount'];

    protected $casts = [
        'purchased_at' => 'date',
        'customer_id' => 'integer',
        'product_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Constrain a query to the transactions $user may see (DESIGN_RBAC.md §4.2):
     * view.all → everything; view.own → transactions of customers the user can
     * see (delegates to Customer::visibleTo, so the two never diverge); otherwise
     * nothing.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(PermissionName::TransactionViewAll->value)) {
            return $query;
        }

        if ($user->can(PermissionName::TransactionViewOwn->value)) {
            // Transactions of the customers this user may see. Subquery (rather
            // than whereHas) keeps the customer-visibility logic in one place.
            return $query->whereIn('customer_id', Customer::visibleTo($user)->select('id'));
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return Attribute<Carbon, never>
     */
    protected function warrantyExpiresAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->purchased_at->copy()->addMonths($this->product->warranty_months),
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isUnderWarranty(): Attribute
    {
        // Coverage runs through the END of the expiry date, not its midnight
        // boundary — a warranty expiring today is still active for all of today.
        return Attribute::make(
            get: fn () => now()->lte($this->warranty_expires_at->endOfDay()),
        );
    }
}
