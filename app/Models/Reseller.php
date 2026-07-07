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
}
