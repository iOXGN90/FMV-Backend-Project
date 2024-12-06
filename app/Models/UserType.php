<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    // Start Foreign Key Connection
    public function users()
    {
        return $this->hasMany(User::class); // A user type can have many users
    }
    // End Foreign Key Connection
}


