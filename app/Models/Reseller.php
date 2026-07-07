<?php

namespace App\Models;

use Database\Factories\ResellerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reseller extends Model
{
    /** @use HasFactory<ResellerFactory> */
    use HasFactory;

    protected $fillable = ['parent_id', 'name'];

    protected $casts = ['parent_id' => 'integer'];

    public function parent()
    {
        return $this->belongsTo(Reseller::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Reseller::class, 'parent_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function transactions()
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
        $stack = $childrenByParent[$this->id] ?? [];

        while ($stack !== []) {
            $id = array_pop($stack);
            $result[] = $id;

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $result;
    }
}
