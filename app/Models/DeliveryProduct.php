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

    // Relationship with ProductDetail
    public function productDetail()
    {
        return $this->belongsTo(ProductDetail::class, 'product_details_id');
    }

    // Relationship through ProductDetail to Product
    public function product()
    {
        return $this->hasOneThrough(
            Product::class, // Final model to fetch
            ProductDetail::class, // Intermediate model
            'id', // Foreign key on ProductDetail table
            'id', // Foreign key on Product table
            'product_details_id', // Local key on DeliveryProduct table
            'product_id' // Local key on ProductDetail table
        );
    }
}

