<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //
    protected $fillable = [
        'actor_id', 'user_id', 'type', 'reference_id','pre_order_id',
        'message','read_status'
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function preOrder()
    {
        return $this->belongsTo(PreOrder::class, 'pre_order_id');
    }
    public function reference(){
        return $this->belongsTo(OrderDetails::class, 'reference_id');

    }
}
