<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_order_id', 'user_id', 'delivery_no', 'notes', 'status', 'created_at', 'updated_at'];

    // Foreign Key Connections
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images()
    {
        return $this->hasMany(Image::class, 'delivery_id');
    }

    public function deliveryProducts()
    {
        return $this->hasMany(DeliveryProduct::class, 'delivery_id');
    }

}
