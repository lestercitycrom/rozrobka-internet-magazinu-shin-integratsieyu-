<?php

use App\Modules\NdiApi\Data\NdiOrderStatusRequest;
use App\Modules\NdiApi\NdiStatusXmlCodec;

test('it builds a status request for one external NDI order', function (): void {
    $xml = (new NdiStatusXmlCodec)->buildRequest(new NdiOrderStatusRequest('NDI-ORDER-0001'), '1220000');
    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue();
    $xpath = new DOMXPath($document);

    expect($document->documentElement?->localName)->toBe('order_status_request')
        ->and($xpath->evaluate('string(/*/*[local-name()="PartyID"])'))->toBe('1220000')
        ->and($xpath->evaluate('string(/*/*[local-name()="ReferencedOrder"]/*[local-name()="OrderReference"]/*[local-name()="DocumentID"])'))->toBe('NDI-ORDER-0001');
});

test('it parses status and tracking without guessing local order state', function (): void {
    $result = (new NdiStatusXmlCodec)->parseResponse(file_get_contents(__DIR__.'/../../Fixtures/Ndi/status_response_completed.xml'));

    expect($result->externalOrderReference)->toBe('NDI-ORDER-0001')
        ->and($result->salesStatus)->toBe('Completed')
        ->and($result->documentStatus)->toBe('Invoice')
        ->and($result->carrier)->toBe('Fixture Carrier')
        ->and($result->tracking)->toHaveCount(1)
        ->and($result->tracking[0]->trackingNumber)->toBe('TRACK-0001');
});
