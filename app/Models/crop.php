<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Crop extends Model
{
    //
     use HasFactory;
    protected $fillable = [
        'farm_id', 'product_id', 'name', 'qty', 'image','status'
        ,'harvested_date'
    ];
    
    public function farm(){
        return $this->belongsTo(Farm::class, 'farm_id');
    }
    public function product(){
        return $this->belongsTo(Product::class, 'product_id');
    }
        public function activities() {
        return $this->hasMany(CropActivity::class);
    }

    public function marketSupplies() {
        return $this->hasMany(FarmMarketSupply::class);
    }

    public function marketTrends() {
        return $this->hasMany(MarketTrend::class);
    }

}
