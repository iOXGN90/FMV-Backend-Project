<?php

namespace App\Models\TEST;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadImageSample extends Model
{
    use HasFactory;

    protected $table = 'image_samples'; // Use the correct table name
    protected $fillable = ['url'];

}
