<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reseller extends Model
{
    //

    protected $fillable = ['parent_id', 'name'];

    public function parent() {
        return $this->belongsTo(Reseller::class, 'parent_id');
    }

    public function children() {
        return $this->hasMany(Reseller::class, 'parent_id');
    }

    public function customers() {
        return $this->hasMany(Customer::class);
    }
}
