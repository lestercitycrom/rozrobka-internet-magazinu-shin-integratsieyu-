<?php

use App\Modules\NdiApi\Data\NdiInquiryLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use App\Modules\NdiApi\NdiA2XmlCodec;

test('it builds the exact A2 namespace shape and preserves a leading-zero SKU', function (): void {
    $xml = (new NdiA2XmlCodec)->buildInquiry(new NdiInquiryRequest(
        documentId: 'INQUIRY-001',
        customerReference: 'CHECKOUT-001',
        lines: [new NdiInquiryLine('1', '000205550016', 4)],
    ), 'PARTY-TEST', 'AGENCY-TEST');

    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->documentElement?->namespaceURI)->toBe(NdiA2XmlCodec::NAMESPACE_URI)
        ->and($document->documentElement?->prefix)->toBe('ew');

    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="OrderLine"]/*[local-name()="OrderedArticle"]/*[local-name()="ArticleIdentification"]/*[local-name()="ManufacturersArticleID"])'))->toBe('000205550016')
        ->and($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="BuyerParty"]/*[local-name()="PartyID"])'))->toBe('PARTY-TEST');
});

test('it safely round-trips XML metacharacters in outbound identifiers', function (): void {
    $xml = (new NdiA2XmlCodec)->buildInquiry(new NdiInquiryRequest(
        documentId: 'INQUIRY&A',
        customerReference: 'CHECKOUT<001>',
        lines: [new NdiInquiryLine('LINE&1', 'SKU<001>&', 1)],
    ), 'PARTY&A', 'AGENCY<TEST>');

    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue();

    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="DocumentID"])'))->toBe('INQUIRY&A')
        ->and($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="CustomerReference"]/*[local-name()="DocumentID"])'))->toBe('CHECKOUT<001>')
        ->and($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="OrderLine"]/*[local-name()="OrderedArticle"]/*[local-name()="ArticleIdentification"]/*[local-name()="ManufacturersArticleID"])'))->toBe('SKU<001>&');
});

test('it parses the supplied A2 response shape without inventing a price', function (): void {
    $result = (new NdiA2XmlCodec)->parseQuote(file_get_contents(__DIR__.'/../../Fixtures/Ndi/quote_a2_success.xml'));

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->documentId)->toBe('QUOTE-TEST-001')
        ->and($result->lines)->toHaveCount(1)
        ->and($result->lines[0]->sku)->toBe('000205550016')
        ->and($result->lines[0]->requestedQuantity)->toBe(4)
        ->and($result->lines[0]->availableQuantity)->toBe(3)
        ->and($result->lines[0]->deliveryDate)->toBe('2026-08-03');
});

test('it returns the PartyID business error without treating it as transport failure', function (): void {
    $result = (new NdiA2XmlCodec)->parseQuote(file_get_contents(__DIR__.'/../../Fixtures/Ndi/quote_a2_party_error.xml'));

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->errorCode)->toBe('915')
        ->and($result->lines)->toBe([]);
});

test('it rejects malformed XML and document types', function (string $xml): void {
    expect(fn () => (new NdiA2XmlCodec)->parseQuote($xml))->toThrow(NdiProtocolException::class);
})->with([
    'malformed' => '<quote_A2>',
    'doctype' => '<!DOCTYPE quote_A2 [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><quote_A2 xmlns="http://www.reifen.net">&xxe;</quote_A2>',
]);
