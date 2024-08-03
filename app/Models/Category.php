<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Start Foreign Key Connection

        public function product(){
            return $this->belongsTo(Product::class);
        }

    // End Foreign Key Connection

}
