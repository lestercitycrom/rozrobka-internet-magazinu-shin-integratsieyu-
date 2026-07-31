<?php

namespace App\Modules\Orders\Data;

use App\Modules\Orders\Models\Order;

final readonly class CreateOrderResult
{
    public function __construct(public Order $order, public bool $created) {}
}
