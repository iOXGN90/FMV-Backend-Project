<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Start Foreign Key Connection

        public function purchaseOrder(){
            return $this->belongsTo(PurchaseOrder::class);
        }

    // End Foreign Key Connection

}
