<?php

namespace App\Modules\NdiApi\Contracts;

use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiC1OrderResult;

interface NdiOrderClient
{
    public function place(NdiC1OrderRequest $request): NdiC1OrderResult;
}
