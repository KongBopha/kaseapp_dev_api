<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PreOrder extends Model
{
    //
    use HasFactory;
    protected $table = 'pre_orders';
    protected $fillable = [
        'user_id', 'product_id', 'crop_id', 'qty','location',
        'note','delivery_date','recurring_schedule',
        'status'
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
    public function product(){
        return $this->belongsTo(Product::class,'product_id');
    }
    public function crop(){
        return $this->belongsTo(crops::class, 'crop_id');
    }
        public function orderDetails() {
        return $this->hasMany(OrderDetail::class);
    }

    public function marketTrends() {
        return $this->hasMany(MarketTrend::class);
    }

}
