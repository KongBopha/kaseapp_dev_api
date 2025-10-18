<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PreOrder;
use App\Models\OrderDetail;
use App\Models\Farm;
use App\Models\Vendor;
use App\Constants\NotificationTypeEnum;

class NotificationService
{
    public function exists(int $preOrderId, int $recipientId, string $type): bool
    {
        return Notification::where('pre_order_id', $preOrderId)
            ->where('recipient_id', $recipientId)
            ->where('type', $type)
            ->exists();
    }

    public function create(array $data): Notification
    {
        return Notification::create($data);
    }

    // Notify all farmers of a new pre-order
    public function notifyFarmers(PreOrder $preOrder): void
    {
        $farms = Farm::with('owner')->get();

        foreach ($farms as $farm) {
            $recipientId = $farm->owner_id;

            if ($this->exists($preOrder->id, $recipientId, NotificationTypeEnum::PRE_ORDER->value)) {
                continue;
            }

            $this->create([
                'recipient_id' => $recipientId,
                'vendor_id'    => $preOrder->user_id,
                'farm_id'      => $farm->id,
                'pre_order_id' => $preOrder->id,
                'type'         => NotificationTypeEnum::PRE_ORDER->value,
                'message'      => "New pre-order: {$preOrder->qty} kg of {$preOrder->product->name}",
                'read_status'  => false,
            ]);
        }
    }

    // Notify vendor when a farm responds
    public function notifyVendor(OrderDetail $orderDetail): ?Notification
    {
        $vendorUserId = $orderDetail->preOrder->user_id;
        $farm = $orderDetail->farm;
        if (!$farm) return null;

        $typeMap = [
            'accepted' => NotificationTypeEnum::ACCEPTANCE->value,
            'rejected' => NotificationTypeEnum::REJECTION->value,
        ];

        $type = $typeMap[$orderDetail->offer_status] ?? NotificationTypeEnum::OFFER->value;

        if ($this->exists($orderDetail->pre_order_id, $vendorUserId, $type)) return null;

        $message = match ($orderDetail->offer_status) {
            'accepted' => "Farm {$farm->name} accepted {$orderDetail->fulfilled_qty} kg.",
            'rejected' => "Farm {$farm->name} rejected your request.",
            default    => "Farm {$farm->name} made an offer of {$orderDetail->fulfilled_qty} kg.",
        };

        return $this->create([
            'recipient_id' => $vendorUserId,
            'vendor_id'    => $orderDetail->preOrder->user_id,
            'farm_id'      => $farm->id,
            'pre_order_id' => $orderDetail->pre_order_id,
            'reference_id' => $orderDetail->id,
            'type'         => $type,
            'message'      => $message,
            'read_status'  => false,
        ]);
    }

    // Notify farmer when vendor responds
    public function notifyFarm(OrderDetail $orderDetail): ?Notification
    {
        $farm = $orderDetail->farm;
        if (!$farm) return null;

        $farmerUserId = $farm->owner_id;

        $typeMap = [
            'confirmed' => NotificationTypeEnum::ACCEPTANCE->value,
            'rejected'  => NotificationTypeEnum::REJECTION->value,
        ];

        $type = $typeMap[$orderDetail->offer_status] ?? NotificationTypeEnum::OFFER->value;

        if ($this->exists($orderDetail->pre_order_id, $farmerUserId, $type)) return null;

        $message = match ($orderDetail->offer_status) {
            'confirmed' => "Vendor confirmed your supply of {$orderDetail->fulfilled_qty} kg.",
            'rejected'  => "Vendor rejected your offer.",
            default     => "Your offer is updated.",
        };

        return $this->create([
            'recipient_id' => $farmerUserId,
            'vendor_id'    => $orderDetail->preOrder->user_id,
            'farm_id'      => $farm->id,
            'pre_order_id' => $orderDetail->pre_order_id,
            'reference_id' => $orderDetail->id,
            'type'         => $type,
            'message'      => $message,
            'read_status'  => false,
        ]);
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->update(['read_status' => true]);
    }
}
