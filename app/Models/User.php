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

    /**
     * The attributes that are mass assignable.
     *
     * @var array

     */
    protected $fillable = [
        'user_type_id',
        'name',
        'email',
        'username',
        'password',
        'number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array

     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array

     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

// Start Foreign Key Connection

    public function userType(){
        return $this->belongsTo(UserType::class);
    }

    public function productRestockOrder(){
        return $this->hasMany(ProductRestockOrder::class);
    }

    public function purchaseOrder(){
        return $this->hasMany(PurchaseOrder::class);
    }
// End Foreign Key Connection

}
