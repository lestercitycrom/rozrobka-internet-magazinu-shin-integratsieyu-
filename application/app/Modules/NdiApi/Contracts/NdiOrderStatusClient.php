<?php

namespace App\Modules\NdiApi\Contracts;

use App\Modules\NdiApi\Data\NdiOrderStatusRequest;
use App\Modules\NdiApi\Data\NdiOrderStatusResult;

interface NdiOrderStatusClient
{
    public function get(NdiOrderStatusRequest $request): NdiOrderStatusResult;
}
