<?php

namespace App\Modules\NdiApi\Data;

use InvalidArgumentException;

final readonly class NdiOrderStatusRequest
{
    public function __construct(public string $externalOrderReference)
    {
        if ($this->externalOrderReference === '' || mb_strlen($this->externalOrderReference) > 50) {
            throw new InvalidArgumentException('NDI order reference must contain 1 to 50 characters.');
        }
    }
}
