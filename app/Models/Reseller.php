<?php

namespace App\Models;

use Database\Factories\ResellerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int|null $customers_count
 */
class Reseller extends Model
{
    /** @use HasFactory<ResellerFactory> */
    use HasFactory;

    protected $fillable = ['parent_id', 'name'];

    protected $casts = ['parent_id' => 'integer'];

    /**
     * @return BelongsTo<Reseller, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'parent_id');
    }

    /**
     * @return HasMany<Reseller, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Reseller::class, 'parent_id');
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Ids of every reseller beneath this one in the tree (all descendants).
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $childrenByParent = [];
        foreach (static::query()->pluck('parent_id', 'id') as $id => $parentId) {
            $childrenByParent[(int) $parentId][] = (int) $id;
        }

        $result = [];
        $seen = [];
        $stack = $childrenByParent[$this->id] ?? [];

        while ($stack !== []) {
            $id = array_pop($stack);

            // Guard against a pathological parent/child cycle in the data.
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $result[] = $id;

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $result;
    }
}
