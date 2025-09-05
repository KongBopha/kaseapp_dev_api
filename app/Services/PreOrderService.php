<?php

namespace App\Services;

use App\Models\PreOrder;
use App\Models\User;
use App\Models\OrderDetail;

class PreOrderService
{
    public function updatePreOrderStatus($preOrderId) {
        $preOrder = PreOrder::findOrFail($preOrderId);
        $totalRequested = $preOrder->qty;

        $totalConfirmed = OrderDetail::where('pre_order_id', $preOrderId)
            ->where('offer_status', 'accepted')
            ->sum('fulfilled_qty');

        if ($totalConfirmed == 0) {
            $status = 'pending';
        } elseif ($totalConfirmed < $totalRequested) {
            $status = 'partially_fulfilled';
        } else {
            $status = 'fulfilled';
        }

        $preOrder->status = $status;
        $preOrder->save();
    }

}
