<?php

namespace App\Services;

use App\Models\PreOrder;
use App\Models\OrderDetail;
use App\Models\MarketSupply;
use App\Models\Crop;

class PreOrderService
{
    // public function updatePreOrderStatus($preOrderId)
    // {
    //     $preOrder = PreOrder::findOrFail($preOrderId);
    //     $totalRequested = $preOrder->qty;

    //     $totalConfirmed = OrderDetail::where('pre_order_id', $preOrderId)
    //         ->whereIn('offer_status', ['accepted', 'confirmed'])
    //         ->sum('fulfilled_qty');

    //     $totalConfirmed = min($totalConfirmed, $totalRequested);

    //     // Update pre-order status
    //     if ($totalConfirmed == 0) {
    //         $status = 'pending';
    //     } elseif ($totalConfirmed < $totalRequested) {
    //         $status = 'partially_fulfilled';
    //     } else {
    //         $status = 'fulfilled';
    //     }

    //     $preOrder->status = $status;
    //     $preOrder->save();

    //     $orderDetails = OrderDetail::where('pre_order_id', $preOrderId)
    //         ->whereIn('offer_status', ['accepted', 'confirmed'])
    //         ->get();

    //     foreach ($orderDetails as $orderDetail) {
    //         if (!$orderDetail->crop_id) continue;

    //         $productId = $orderDetail->preOrder->product_id;

    //         $marketSupply = MarketSupply::firstOrCreate(
    //             [
    //                 'farm_id'    => $orderDetail->farm_id,
    //                 'crop_id'    => $orderDetail->crop_id,
    //                 'product_id' => $productId,
    //             ],
    //             [
    //                 'available_qty' => 0,
    //                 'unit'          => $orderDetail->crop->unit ?? 'kg',
    //                 'availability'  => $orderDetail->crop->harvest_date ?? now()->toDateString(),
    //             ]
    //         );

    //         // Calculate remaining quantity
    //         $remainingQty = max(0, $orderDetail->crop->qty - $orderDetail->fulfilled_qty);
    //         $marketSupply->available_qty = $remainingQty;
    //         $marketSupply->save();
    //     }

    //     return $preOrder;
    // }
        public function updatePreOrderStatus($preOrderId)
    {
        $preOrder = PreOrder::findOrFail($preOrderId);
        $totalRequested = $preOrder->qty;

        // Sum of confirmed quantities from order details
        $totalConfirmed = OrderDetail::where('pre_order_id', $preOrderId)
            ->where('offer_status', 'confirmed')
            ->sum('fulfilled_qty');

        // Cap confirmed qty to requested qty
        $totalConfirmed = min($totalConfirmed, $totalRequested);

        // Update pre-order status
        if ($totalConfirmed == 0) {
            $preOrder->status = 'pending';
        } elseif ($totalConfirmed < $totalRequested) {
            $preOrder->status = 'partially_fulfilled';
        } else {
            $preOrder->status = 'fulfilled';
        }

        $preOrder->save();

        return $preOrder;
    }
}
