<?php

namespace App\Models;

use App\Support\TeamRoleLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An admin-set display-label override for a role slug (L3 white-label, D1).
 * Never read directly — resolution lives in TeamRoleLabels (override → config
 * default → headline fallback), which caches the whole map. Every write through
 * this model flushes that cache, so an update is visible on the next request
 * without any deploy.
 *
 * ⚠️ Cache invalidation rides on MODEL EVENTS (saved/deleted below): writes
 * MUST go through model instances — create()/update()/delete() on an instance.
 * Query-builder bulk paths (LabelOverride::query()->update()/->delete(),
 * upsert(), DB::table()) bypass Eloquent events and leave the cached map
 * STALE until someone else writes. The L3-C settings UI must respect this.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LabelOverride extends Model
{
    protected $fillable = ['key', 'label'];

    protected static function booted(): void
    {
        static::saved(fn () => TeamRoleLabels::flush());
        static::deleted(fn () => TeamRoleLabels::flush());
    }
}
