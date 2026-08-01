<?php

namespace App\Modules\Cart\Data;

final readonly class CartServiceIssue
{
    public function __construct(public string $code, public string $reason) {}
}
