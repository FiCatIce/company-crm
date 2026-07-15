<?php

namespace App\Models;

use App\Enums\InteractionDirection;
use App\Enums\InteractionOutcome;
use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Enums\PermissionName;
use Database\Factories\InteractionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $user_id
 * @property InteractionType $type
 * @property InteractionDirection|null $direction
 * @property string|null $subject
 * @property string|null $body
 * @property InteractionOutcome|null $outcome
 * @property int|null $duration_sec
 * @property Carbon $occurred_at
 * @property InteractionSource $source
 * @property string|null $external_ref
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Interaction extends Model
{
    /** @use HasFactory<InteractionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'user_id',
        'type',
        'direction',
        'subject',
        'body',
        'outcome',
        'duration_sec',
        'occurred_at',
        'source',
        'external_ref',
        'meta',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'user_id' => 'integer',
        'type' => InteractionType::class,
        'direction' => InteractionDirection::class,
        'outcome' => InteractionOutcome::class,
        'source' => InteractionSource::class,
        'duration_sec' => 'integer',
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Constrain a query to the interactions (call log) $user may see
     * (DESIGN_RBAC.md §4.2): view.all → everything; view.own → interactions of
     * customers the user can see (delegates to Customer::visibleTo so call-log
     * visibility never diverges from customer visibility); otherwise nothing.
     *
     * @param  Builder<Interaction>  $query
     * @return Builder<Interaction>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(PermissionName::InteractionViewAll->value)) {
            return $query;
        }

        if ($user->can(PermissionName::InteractionViewOwn->value)) {
            // Subquery (rather than whereHas) keeps the customer-visibility logic
            // in one place and stays analysable by static analysis.
            return $query->whereIn('customer_id', Customer::visibleTo($user)->select('id'));
        }

        return $query->whereRaw('1 = 0');
    }
}
