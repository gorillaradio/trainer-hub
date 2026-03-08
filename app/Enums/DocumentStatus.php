<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Expired = 'expired';
    case ExpiringSoon = 'expiring_soon';
}
