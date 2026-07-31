<?php

namespace App\Modules\NdiApi\Enums;

enum NdiSubmissionStatus: string
{
    case Sending = 'sending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
}
