<?php

use App\Modules\NdiApi\Data\NdiDespatchRequest;
use App\Modules\NdiApi\NdiDespatchXmlCodec;

test('it builds the confirmed C1 despatch request profile', function (): void {
    $xml = (new NdiDespatchXmlCodec)->buildRequest(new NdiDespatchRequest('NDI-ORDER-0001'), '1220000', '91');
    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->documentElement?->namespaceURI)->toBe(NdiDespatchXmlCodec::NAMESPACE_URI);
    $xpath = new DOMXPath($document);

    expect($xpath->evaluate('string(/*/*[local-name()="References"]/*[local-name()="ReferenceTypeCode"])'))->toBe('VN')
        ->and($xpath->evaluate('string(/*/*[local-name()="References"]/*[local-name()="DocumentID"])'))->toBe('NDI-ORDER-0001')
        ->and($xpath->evaluate('string(/*/*[local-name()="BuyerParty"]/*[local-name()="PartyID"])'))->toBe('1220000');
});

test('it parses despatch tracking after picking', function (): void {
    $result = (new NdiDespatchXmlCodec)->parseResponse(file_get_contents(__DIR__.'/../../Fixtures/Ndi/desadv_response_success.xml'));

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->documents)->toHaveCount(1)
        ->and($result->documents[0]->documentNumber)->toBe('DESADV-0001')
        ->and($result->documents[0]->tracking)->toHaveCount(1)
        ->and($result->documents[0]->tracking[0]->trackingNumber)->toBe('TRACK-0001');
});
