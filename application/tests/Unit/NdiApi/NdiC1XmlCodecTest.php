<?php

use App\Modules\NdiApi\Data\NdiC1OrderLine;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use App\Modules\NdiApi\NdiC1XmlCodec;

test('it builds the confirmed C1 shape and omits an alternate consignee', function (): void {
    $xml = (new NdiC1XmlCodec)->buildOrder(new NdiC1OrderRequest(
        customerReference: 'EASY-TEST-0001',
        lines: [new NdiC1OrderLine('1', '4765205551649102', 2)],
    ), '1220000', '91');

    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->documentElement?->namespaceURI)->toBe(NdiC1XmlCodec::NAMESPACE_URI);

    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(/*[local-name()="order"]/*[local-name()="DocumentID"])'))->toBe('C1')
        ->and($xpath->evaluate('string(/*[local-name()="order"]/*[local-name()="BuyerParty"]/*[local-name()="PartyID"])'))->toBe('1220000')
        ->and($xpath->evaluate('string(/*[local-name()="order"]/*[local-name()="OrderLine"]/*[local-name()="OrderedArticle"]/*[local-name()="ArticleIdentification"]/*[local-name()="BuyersArticleID"])'))->toBe('4765205551649102')
        ->and($xpath->evaluate('string(/*[local-name()="order"]/*[local-name()="OrderLine"]/*[local-name()="OrderedArticle"]/*[local-name()="RequestedQuantity"]/*[local-name()="QuantityValue"])'))->toBe('2')
        ->and($xpath->evaluate('count(/*[local-name()="order"]/*[local-name()="Consignee"])'))->toBe(0.0);
});

test('it parses a successful C1 response into explicit acceptance fields', function (): void {
    $result = (new NdiC1XmlCodec)->parseOrderResponse(file_get_contents(__DIR__.'/../../Fixtures/Ndi/c1_order_response_success.xml'));

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->customerReference)->toBe('EASY-TEST-0001')
        ->and($result->lines)->toHaveCount(1)
        ->and($result->lines[0]->externalOrderReference)->toBe('NDI-ORDER-0001')
        ->and($result->lines[0]->confirmedQuantity)->toBe(2)
        ->and($result->lines[0]->deliveryDate)->toBe('2030-01-03');
});

test('it rejects a successful C1 header without order lines', function (): void {
    $xml = '<order_response xmlns="http://www.reifen.net"><DocumentID>C1</DocumentID><ErrorHead><ErrorCode>0</ErrorCode></ErrorHead><CustomerReference><DocumentID>X</DocumentID></CustomerReference></order_response>';

    expect(fn () => (new NdiC1XmlCodec)->parseOrderResponse($xml))->toThrow(NdiProtocolException::class);
});
