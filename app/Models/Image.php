<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Delivery; // Ensure this line is correct according to your actual namespace structure

class Image extends Model
{
    use HasFactory;

    protected $table = 'images'; // Explicitly defining the table is optional if the table name follows Laravel's naming convention
    protected $fillable = ['url', 'delivery_id'];

    /**
     * Get the delivery associated with the image.
     */
    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
