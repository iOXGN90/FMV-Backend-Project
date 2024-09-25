<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_id', 'image_url'];

    // Start Foreign Key Connection

        public function delivery(){
            return $this->belongsTo(Delivery::class);
        }

    // End Foreign Key Connection

}
