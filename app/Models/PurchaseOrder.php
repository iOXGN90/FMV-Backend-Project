<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Start Foreign Key Connections

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function productDetails()
    {
        return $this->hasMany(ProductDetail::class, 'purchase_order_id', 'id');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'purchase_order_id', 'id'); // Rename to 'deliveries' for plural naming convention
    }

    public function saleType()
    {
        return $this->belongsTo(SaleType::class);
    }

    // End Foreign Key Connections
}

