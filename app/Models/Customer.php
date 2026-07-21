<?php

namespace App\Models;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\PermissionName;
use App\Support\HierarchyResolver;
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
 * @property string|null $reseller_name_legacy
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
    protected $fillable = ['assigned_to', 'name', 'phone', 'email', 'address', 'status', 'source'];

    protected $casts = [
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
     * Constrain a query to the customers $user may see (DESIGN_RBAC.md §4.2a +
     * DESIGN_HIERARCHY.md H3):
     *   - customer.view.all → everything (a truly global role; unchanged)
     *   - otherwise → the UNION of the hierarchy tiers the user holds:
     *       · view.team     → every customer owned by a member of their team(s)
     *       · view.own      → created_by OR assigned_to = $user (Sales; D1-B)
     *       · view.assigned → every customer owned by a sales who assigned them
     *     resolved to owner user-ids by HierarchyResolver.
     *   - no tier → nothing
     *
     * The single source of truth for "which customers can this user reach" — every
     * customer read path funnels through here (index, search, and, via the policy,
     * show). Transaction/Interaction visibility delegate to it, so the roll-up
     * propagates to money and call-log for free. Sales behaviour is unchanged: a
     * view.own-only user resolves to [self], i.e. exactly created_by/assigned_to = me.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(PermissionName::CustomerViewAll->value)) {
            return $query;
        }

        $ownerIds = HierarchyResolver::visibleOwnerIds($user);

        if ($ownerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        // Nested group so the OR never escapes to broaden a caller's outer AND.
        return $query->where(function (Builder $scoped) use ($ownerIds) {
            $scoped->whereIn('created_by', $ownerIds)
                ->orWhereIn('assigned_to', $ownerIds);
        });
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
