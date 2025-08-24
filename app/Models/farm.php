<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Farm extends Model
{
    //
     use HasFactory;
    protected $fillable = [
        'owner_id', 'name', 'address', 'about', 'status', 'cover', 
        'logo'
    ];
    public function owner(){
        return $this->belongsTo(User::class,'owner_id');
    }
    public function crops(){
        return $this->hasMany(Crop::class);
    }
    public function marketSupplies() {
        return $this->hasMany(FarmMarketSupply::class);
    }

}
