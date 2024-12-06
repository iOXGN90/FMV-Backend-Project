<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    // use SoftDeletes;

    protected $guarded = [];

    // Start Foreign Key Connection

    // A product belongs to one category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productDetails()
    {
        return $this->hasMany(ProductDetail::class);
    }

    public function productRestockOrders()
    {
        return $this->hasMany(ProductRestockOrder::class);
    }

    public function returns()
    {
        return $this->hasMany(Returns::class);
    }

    public function deliveryProduct()
    {
        return $this->hasMany(DeliveryProduct::class);
    }

    // End Foreign Key Connection
}
