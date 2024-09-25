<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Start Foreign Key Connection
        public function product()
        {
            return $this->belongsTo(Product::class);
        }


        public function purchaseOrder(){
            return $this->belongsTo(PurchaseOrder::class);
        }

        public function deliveryProduct(){
            return $this->hasMany(DeliveryProduct::class);
        }
    // End Foreign Key Connection

}
