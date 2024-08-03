<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Start Foreign Key Connection

        public function user(){
            return $this->belongsTo(User::class);
        }

        public function address(){
            return $this->belongsTo(Address::class);
        }

        public function productDetails(){
            return $this->hasMany(ProductDetail::class);
        }

        public function delivery(){
            return $this->hasMany(Delivery::class);
        }

        public function saleType(){
            return $this->belongsTo(SaleType::class);
        }

    // End Foreign Key Connection

}
