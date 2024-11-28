<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Returns extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function deliveryProduct()
    {
        return $this->belongsTo(DeliveryProduct::class, 'delivery_product_id');
    }


}
