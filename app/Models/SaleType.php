<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    // Start Foreign Key Connection
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class); // A sale type can have many purchase orders
    }
    // End Foreign Key Connection
}
