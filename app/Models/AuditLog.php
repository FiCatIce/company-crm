<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only audit record (DESIGN_RBAC.md D2). Written whenever an actor
 * changes another account's role/permissions or CRUDs a user. Never updated.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $target_user_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 * @property Carbon|null $created_at
 */
#[Fillable(['actor_id', 'target_user_id', 'action', 'changes'])]
class AuditLog extends Model
{
    /** An audit row is immutable — it only ever carries a creation time. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /**
     * Record an audit entry. `$changes` is a free-form diff (e.g. role from→to,
     * permissions added/removed); pass null when there is nothing to diff.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function record(?User $actor, ?User $target, string $action, ?array $changes = null): self
    {
        return self::create([
            'actor_id' => $actor?->id,
            'target_user_id' => $target?->id,
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
