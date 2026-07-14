<?php

namespace App\Models;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\PermissionName;
use App\Support\PhoneNormalizer;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $reseller_id
 * @property int|null $assigned_to
 * @property int|null $created_by
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

    // created_by is deliberately NOT fillable: it is the immutable "who entered
    // this customer" attribution, set server-side on create so it cannot be
    // forged via mass assignment (DESIGN_RBAC.md §4.1).
    protected $fillable = ['reseller_id', 'assigned_to', 'name', 'phone', 'email', 'address', 'status', 'source'];

    protected $casts = [
        'reseller_id' => 'integer',
        'assigned_to' => 'integer',
        'created_by' => 'integer',
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
     * The staff member who first entered this customer (immutable). The Sales
     * visibility gate; coexists with the mutable owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Constrain a query to the customers $user may see (DESIGN_RBAC.md §4.2a):
     *   - customer.view.all → everything (managers/CS/maintenance/admin today)
     *   - customer.view.own → created_by OR assigned_to = $user (Sales; D1-B)
     *   - otherwise → nothing
     *
     * The single source of truth for "which customers can this user reach" —
     * every customer read path funnels through here (index, search, and, via the
     * policy, show).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(PermissionName::CustomerViewAll->value)) {
            return $query;
        }

        if ($user->can(PermissionName::CustomerViewOwn->value)) {
            return $query->where(function (Builder $scoped) use ($user) {
                $scoped->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
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
