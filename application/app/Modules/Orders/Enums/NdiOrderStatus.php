<?php

namespace App\Modules\Orders\Enums;

enum NdiOrderStatus: string
{
    case NotSubmitted = 'not_submitted';
    case AwaitingApproval = 'awaiting_approval';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
}
