<?php

namespace App\Modules\NdiApi\Contracts;

use App\Modules\NdiApi\Data\NdiDespatchRequest;
use App\Modules\NdiApi\Data\NdiDespatchResult;

interface NdiDespatchClient
{
    public function get(NdiDespatchRequest $request): NdiDespatchResult;
}
