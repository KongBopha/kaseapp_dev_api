<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends Model
{
    //
    use HasFactory;
    protected $fillable = [
        'owner_id', 'name', 'vendor_type', 'about','address',
        'logo'
    ];
    
    public function owner(){
        return $this->belongsTo(User::class, 'owner_id');
    }
}
