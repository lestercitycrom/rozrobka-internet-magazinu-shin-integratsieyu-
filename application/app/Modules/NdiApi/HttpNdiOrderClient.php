<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiC1OrderResult;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;

final class HttpNdiOrderClient implements NdiOrderClient
{
    public function __construct(
        private readonly NdiC1XmlCodec $codec,
        private readonly NdiHttpTransport $transport,
    ) {}

    public function place(NdiC1OrderRequest $request): NdiC1OrderResult
    {
        if (! config('ndi_api.order_enabled', false)) {
            throw new NdiApiDisabled('NDI C1 order submission is disabled.');
        }

        $xml = $this->codec->buildOrder(
            $request,
            (string) config('ndi_api.party_id'),
            (string) config('ndi_api.agency_code'),
        );

        return $this->codec->parseOrderResponse($this->transport->post($xml, 'C1'));
    }
}
