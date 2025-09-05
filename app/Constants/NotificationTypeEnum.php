<?php

namespace App\Constants;

enum NotificationTypeEnum: string {
    case PRE_ORDER    = 'pre_order';
    case ACCEPTANCE   = 'acceptance';
    case REJECTION    = 'rejection';
    case OFFER        = 'offer';
}
