<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDetail extends Model
{
    //
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    
     use HasFactory;
    protected $fillable = [
        'pre_order_id', 'farm_id','crop_id','product_id','fulfilled_qty',
        'description','offer_status'
    ];

    public function preOrder() {
        return $this->belongsTo(PreOrder::class, 'pre_order_id');
    }

    public function farm() {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function product() {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function crop() {
        return $this->belongsTo(Crop::class, 'crop_id');
    }

}
