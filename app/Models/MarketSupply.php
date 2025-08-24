<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSupply extends Model
{
    //
    protected $fillable = [
        'farm_id',
        'crop_id',
        'product_id',
        'available_qty',
        'unit',
        'availability',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }
    public function product() 
    {
        return $this->belongsTo(Product::class);
    }
}
