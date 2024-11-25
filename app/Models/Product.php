<?php

namespace App\Models;

use App\Http\Controllers\API\CategoryController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Start Foreign Key Connection

        public function category(){
            return $this->belongsTo(Category::class);
        }

        public function productDetails(){
            return $this->hasMany(ProductDetail::class);
        }

        public function productRestockOrders(){
            return $this->hasMany(ProductRestockOrder::class);
        }

        public function damage(){
            return $this->hasMany(Damage::class);
        }

    // End Foreign Key Connection



}
