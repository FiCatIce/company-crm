<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['reseller_id', 'name', 'phone', 'email', 'address'];

    public function reseller()
    {
        return $this->belongsTo(Reseller::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
