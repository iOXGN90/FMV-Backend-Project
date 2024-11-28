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

    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function returns(){
        return $this->hasMany(Returns::class);
    }

}
