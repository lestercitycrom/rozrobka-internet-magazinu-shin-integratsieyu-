<?php

namespace App\Modules\NdiApi\Data;

use InvalidArgumentException;

final readonly class NdiDespatchRequest
{
    public function __construct(public string $externalOrderReference)
    {
        if ($this->externalOrderReference === '' || mb_strlen($this->externalOrderReference) > 50) {
            throw new InvalidArgumentException('NDI despatch reference must contain 1 to 50 characters.');
        }
    }
}
