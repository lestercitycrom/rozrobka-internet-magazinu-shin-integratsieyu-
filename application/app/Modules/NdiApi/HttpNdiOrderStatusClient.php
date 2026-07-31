<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiOrderStatusClient;
use App\Modules\NdiApi\Data\NdiOrderStatusRequest;
use App\Modules\NdiApi\Data\NdiOrderStatusResult;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;

final class HttpNdiOrderStatusClient implements NdiOrderStatusClient
{
    public function __construct(
        private readonly NdiStatusXmlCodec $codec,
        private readonly NdiHttpTransport $transport,
    ) {}

    public function get(NdiOrderStatusRequest $request): NdiOrderStatusResult
    {
        if (! config('ndi_api.fulfilment_read_enabled', false)) {
            throw new NdiApiDisabled('NDI order status reads are disabled.');
        }

        $xml = $this->codec->buildRequest($request, (string) config('ndi_api.party_id'));

        return $this->codec->parseResponse($this->transport->post($xml, 'Status'));
    }
}
