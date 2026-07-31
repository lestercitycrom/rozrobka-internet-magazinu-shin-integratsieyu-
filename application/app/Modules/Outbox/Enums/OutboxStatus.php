<?php

namespace App\Modules\Outbox\Enums;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
