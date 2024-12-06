<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'user_type_id',
        'name',
        'email',
        'username',
        'password',
        'number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Start Foreign Key Connection
    public function userType()
    {
        return $this->belongsTo(UserType::class);
    }

    public function productRestockOrder()
    {
        return $this->hasMany(ProductRestockOrder::class);
    }

    public function purchaseOrder()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
    // End Foreign Key Connection
}
