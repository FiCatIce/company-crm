<?php

namespace App\Models;

use App\Enums\InteractionDirection;
use App\Enums\InteractionOutcome;
use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use Database\Factories\InteractionFactory;
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
}
