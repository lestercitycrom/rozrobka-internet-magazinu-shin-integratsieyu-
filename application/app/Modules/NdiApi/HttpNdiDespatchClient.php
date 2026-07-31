<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiDespatchClient;
use App\Modules\NdiApi\Data\NdiDespatchRequest;
use App\Modules\NdiApi\Data\NdiDespatchResult;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;

final class HttpNdiDespatchClient implements NdiDespatchClient
{
    public function __construct(
        private readonly NdiDespatchXmlCodec $codec,
        private readonly NdiHttpTransport $transport,
    ) {}

    public function get(NdiDespatchRequest $request): NdiDespatchResult
    {
        if (! config('ndi_api.fulfilment_read_enabled', false)) {
            throw new NdiApiDisabled('NDI despatch reads are disabled.');
        }

        $xml = $this->codec->buildRequest(
            $request,
            (string) config('ndi_api.party_id'),
            (string) config('ndi_api.agency_code'),
        );

        return $this->codec->parseResponse($this->transport->post($xml, 'DesAdv'));
    }
}
