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
        private readonly NdiIntegrationProfileResolver $profiles,
    ) {}

    public function place(NdiC1OrderRequest $request): NdiC1OrderResult
    {
        $settings = $this->profiles->forOrder($request->integrationProfile);
        if ($request->integrationProfile === NdiIntegrationProfile::SANDBOX) {
            $this->assertSandboxLines($request);
        }

        $xml = $this->codec->buildOrder(
            $request,
            $settings->partyId,
            $settings->agencyCode,
        );

        return $this->codec->parseOrderResponse($this->transport->post($xml, 'C1', $settings));
    }

    private function assertSandboxLines(NdiC1OrderRequest $request): void
    {
        if (count($request->lines) !== 1
            || $request->lines[0]->buyersArticleId !== NdiSandboxContract::ARTICLE_ID
            || $request->lines[0]->quantity !== NdiSandboxContract::QUANTITY) {
            throw new NdiApiDisabled('NDI sandbox C1 rejected a non-approved article or quantity.');
        }
    }
}
