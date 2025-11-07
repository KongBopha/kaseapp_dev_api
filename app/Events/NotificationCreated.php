<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

// class NotificationCreated implements ShouldBroadcastNow
// {
//     use SerializesModels;

//     public $notification;

//     /**
//      * Create a new event instance.
//      */
//     public function __construct(Notification $notification)
//     {
//         $this->notification = $notification;
//     }

//     /**
//      * The channel the event should broadcast on.
//      */
//     public function broadcastOn(): Channel
//     {
//         return new Channel('notifications.' . $this->notification->recipient_id);
//     }

//     /**
//      * The name of the event (used in frontend listener)
//      */
//     public function broadcastAs(): string
//     {
//         return 'NotificationCreated';
//     }

//     /**
//      * The data to broadcast.
//      */
//     public function broadcastWith(): array
//     {
//         return [
//             'id'          => $this->notification->id,
//             'type'        => $this->notification->type,
//             'message'     => $this->notification->message,
//             'read_status' => $this->notification->read_status,  
//             'created_at'  => $this->notification->created_at->toDateTimeString(),
//             'vendor_id'   => $this->notification->vendor_id,
//             'pre_order_id'=> $this->notification->pre_order_id,
//         ];
//     }

// }
