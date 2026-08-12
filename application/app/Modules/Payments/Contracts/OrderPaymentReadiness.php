<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Orders\Models\Order;

interface OrderPaymentReadiness
{
    public function assertReady(Order $order): void;
}
