<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    //
     use HasFactory;
    protected $fillable = [
        'owner_id','name', 'description', 'unit', 'image'
    ];

    public function owner(){
        return $this->belongsTo(User::class, 'owner_id');
    }
    
}
