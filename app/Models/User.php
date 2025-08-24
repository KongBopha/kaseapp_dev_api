<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable,HasApiTokens;

    // Fillable fields for mass assignment
    protected $fillable = [
        'first_name',
        'last_name',
        'sex',
        'profile_url',
        'email',
        'phone',
        'role',
        'user_type',
        'address',
        'password'
    ];

    
    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Relationships

    // A user can have multiple farms
    public function farms()
    {
        return $this->hasMany(Farm::class, 'owner_id');
    }

    // A user can have multiple vendors (if role = vendor)
    public function vendors()
    {
        return $this->hasMany(Vendor::class, 'owner_id');
    }

    // A user can have multiple pre-orders
    public function preOrders()
    {
        return $this->hasMany(PreOrder::class);
    }

    // A user can have multiple crop activities
    public function cropActivities()
    {
        return $this->hasMany(CropActivity::class);
    }
}
