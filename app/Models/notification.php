<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //
    protected $fillable = [
        'vendor_id', 'farm_id', 'type','recipient_id' ,'reference_id','pre_order_id',
        'message','read_status'
    ];

    public function recipient(){
        return $this->belongsTo(User::class, 'recipient_id');
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }
    public function preOrder()
    {
        return $this->belongsTo(PreOrder::class, 'pre_order_id');
    }
    public function reference(){
        return $this->belongsTo(OrderDetail::class, 'reference_id');

    }
}

