<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case SETTLEMENT = 'settlement';
    case EXPIRE = 'expire';
    case CANCEL = 'cancel';
    case DENY = 'deny';

    public function isSuccess(): bool
    {
        return $this === self::SETTLEMENT;
    }
}
