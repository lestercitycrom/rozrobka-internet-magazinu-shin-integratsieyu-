<?php

namespace App\Modules\NdiApi\Contracts;

use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Data\NdiQuoteResult;

interface NdiInquiryClient
{
    public function inquire(NdiInquiryRequest $request): NdiQuoteResult;
}
