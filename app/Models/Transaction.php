<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'product_id', 'reseller_id', 'purchased_at'];

    protected $casts = [
        'purchased_at' => 'date',
        'customer_id' => 'integer',
        'product_id' => 'integer',
        'reseller_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class);
    }

    protected function warrantyExpiresAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->purchased_at->copy()->addMonths($this->product->warranty_months),
        );
    }

    protected function isUnderWarranty(): Attribute
    {
        return Attribute::make(
            get: fn () => now()->lte($this->warranty_expires_at),
        );
    }
}
