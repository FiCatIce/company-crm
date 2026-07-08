<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
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

    protected $fillable = ['customer_id', 'product_id', 'reseller_id', 'purchased_at', 'amount'];

    protected $casts = [
        'purchased_at' => 'date',
        'customer_id' => 'integer',
        'product_id' => 'integer',
        'reseller_id' => 'integer',
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
     * @return BelongsTo<Reseller, $this>
     */
    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
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
