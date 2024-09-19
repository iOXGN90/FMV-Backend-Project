<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Foreign Key Connections
    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    // public function product(){
    //     return $this->belongsTo(Product::class);
    // }

    public function productDetail()
    {
        return $this->belongsTo(ProductDetail::class);
    }

    public function damages()
    {
        return $this->hasMany(Damage::class);
    }
}
