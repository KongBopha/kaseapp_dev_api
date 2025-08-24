<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Send notification to a specific user
     */
    public function sendToUser($actorId, $userId, $type, $message, $preOrderId = null, $referenceId = null)
    {
        return Notification::create([
            'actor_id' => $actorId,
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'pre_order_id' => $preOrderId,
            'reference_id' => $referenceId,
            'read_status' => 0
        ]);
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers($actorId, $userIds, $type, $message, $preOrderId = null, $referenceId = null)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = $this->sendToUser($actorId, $userId, $type, $message, $preOrderId, $referenceId);
        }
        return $notifications;
    }
}
