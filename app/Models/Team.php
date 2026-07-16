<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A team — the org unit a book of customers rolls up to (DESIGN_HIERARCHY.md DH1).
 *
 * Relations are defined but NOT yet used by any scope/dashboard/route; H1 is
 * dormant structure only. `parent()`/`children()` are the L4 hook for a nested
 * region/division tree.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int|null $parent_id
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $fillable = ['name', 'type', 'parent_id'];

    protected $casts = ['parent_id' => 'integer'];

    /**
     * Members of this team (any role). Membership is a pivot so DH6 multi-team
     * needs no schema change; DH2's one-team rule is enforced at creation time.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role_in_team')
            ->withTimestamps();
    }

    /**
     * Parent team (L4 hook: region/division tree). Unused in L1.
     *
     * @return BelongsTo<Team, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_id');
    }

    /**
     * Child teams (L4 hook). Unused in L1.
     *
     * @return HasMany<Team, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Team::class, 'parent_id');
    }
}
