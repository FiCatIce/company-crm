<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    //

    protected $fillable = ['reseller_id', 'name', 'phone', 'email', 'address'];

    public function reseller() {
        return $this->belongsTo(Reseller::class);
    }

    public function transactions() {
        return $this->hasMany(Transaction::class);
    }
}
