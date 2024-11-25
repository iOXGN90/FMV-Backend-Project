<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Damage extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Foreign Key Connections
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

}
