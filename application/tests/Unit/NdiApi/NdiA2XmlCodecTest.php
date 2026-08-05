<?php

use App\Modules\NdiApi\Data\NdiInquiryLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use App\Modules\NdiApi\NdiA2XmlCodec;

test('it builds the exact A2 namespace shape and preserves a leading-zero SKU', function (): void {
    $xml = (new NdiA2XmlCodec)->buildInquiry(new NdiInquiryRequest(
        documentId: 'A2',
        customerReference: 'CHECKOUT-001',
        lines: [new NdiInquiryLine('1', '000205550016', 4)],
    ), 'PARTY-TEST', 'AGENCY-TEST');

    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->documentElement?->namespaceURI)->toBe(NdiA2XmlCodec::NAMESPACE_URI)
        ->and($document->documentElement?->prefix)->toBe('ew');

    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="OrderLine"]/*[local-name()="OrderedArticle"]/*[local-name()="ArticleIdentification"]/*[local-name()="EANUCCArticleID"])'))->toBe('000205550016')
        ->and($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="BuyerParty"]/*[local-name()="PartyID"])'))->toBe('PARTY-TEST')
        ->and($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="Variant"])'))->toBe('5')
        ->and(collect($document->documentElement?->childNodes)
            ->filter(fn ($node): bool => $node instanceof DOMElement)
            ->map(fn (DOMElement $node): string => $node->localName)
            ->values()
            ->all())->toBe(['DocumentID', 'Variant', 'CustomerReference', 'BuyerParty', 'OrderLine']);
});

test('it safely round-trips XML metacharacters in outbound identifiers', function (): void {
    $xml = (new NdiA2XmlCodec)->buildInquiry(new NdiInquiryRequest(
        documentId: 'A2',
        customerReference: 'CHECKOUT<001>',
        lines: [new NdiInquiryLine('LINE&1', 'SKU<001>&', 1)],
    ), 'PARTY&A', 'AGENCY<TEST>');

    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue();

    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="DocumentID"])'))->toBe('A2')
        ->and($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="CustomerReference"]/*[local-name()="DocumentID"])'))->toBe('CHECKOUT<001>')
        ->and($xpath->evaluate('string(/*[local-name()="inquiry_A2"]/*[local-name()="OrderLine"]/*[local-name()="OrderedArticle"]/*[local-name()="ArticleIdentification"]/*[local-name()="EANUCCArticleID"])'))->toBe('SKU<001>&');
});

test('it parses the EANUCC identifier returned by the confirmed NDI example', function (): void {
    $xml = str_replace(
        ['ManufacturersArticleID', '000205550016'],
        ['EANUCCArticleID', '4765205551649102'],
        file_get_contents(__DIR__.'/../../Fixtures/Ndi/quote_a2_success.xml'),
    );

    $result = (new NdiA2XmlCodec)->parseQuote($xml);

    expect($result->lines[0]->sku)->toBe('4765205551649102');
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
