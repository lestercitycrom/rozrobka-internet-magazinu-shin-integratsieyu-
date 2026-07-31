<?php

namespace App\Modules\NdiApi\Data;

use InvalidArgumentException;

final readonly class NdiC1OrderLine
{
    public function __construct(
        public string $lineId,
        public string $buyersArticleId,
        public int $quantity,
    ) {
        if ($this->lineId === '' || mb_strlen($this->lineId) > 50) {
            throw new InvalidArgumentException('NDI C1 line ID must contain 1 to 50 characters.');
        }

        if ($this->buyersArticleId === '' || mb_strlen($this->buyersArticleId) > 100) {
            throw new InvalidArgumentException('NDI C1 article ID must contain 1 to 100 characters.');
        }

        if ($this->quantity < 1) {
            throw new InvalidArgumentException('NDI C1 quantity must be positive.');
        }
    }
}
