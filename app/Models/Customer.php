<?php

namespace App\Models;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Support\PhoneNormalizer;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $reseller_id
 * @property int|null $assigned_to
 * @property string $name
 * @property string|null $phone
 * @property string|null $phone_normalized
 * @property string|null $email
 * @property string|null $address
 * @property CustomerStatus $status
 * @property CustomerSource|null $source
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['reseller_id', 'assigned_to', 'name', 'phone', 'email', 'address', 'status', 'source'];

    protected $casts = [
        'reseller_id' => 'integer',
        'assigned_to' => 'integer',
        'status' => CustomerStatus::class,
        'source' => CustomerSource::class,
    ];

    /**
     * Keep phone_normalized (E.164) in sync whenever phone is set, so CTI
     * caller-ID lookups have a canonical column. Human phone is stored verbatim.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'phone' => $value,
                'phone_normalized' => PhoneNormalizer::e164($value),
            ],
        );
    }

    /**
     * @return BelongsTo<Reseller, $this>
     */
    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    /**
     * The staff member who owns this customer (attribution/filtering only).
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Interaction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }
}
