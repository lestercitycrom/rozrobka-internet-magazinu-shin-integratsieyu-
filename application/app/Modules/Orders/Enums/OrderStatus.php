<?php

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case PendingReview = 'pending_review';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case FulfilmentPending = 'fulfilment_pending';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
