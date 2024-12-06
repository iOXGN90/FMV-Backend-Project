<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    // Start Foreign Key Connection

    // A category can have many products
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // End Foreign Key Connection
}
