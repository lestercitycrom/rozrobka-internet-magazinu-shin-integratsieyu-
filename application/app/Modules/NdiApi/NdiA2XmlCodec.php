<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Data\NdiInquiryLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Data\NdiQuoteLine;
use App\Modules\NdiApi\Data\NdiQuoteResult;
use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class NdiA2XmlCodec
{
    public const NAMESPACE_URI = 'http://www.reifen.net';

    public function buildInquiry(NdiInquiryRequest $request, string $partyId, string $agencyCode): string
    {
        if ($partyId === '' || $agencyCode === '') {
            throw new NdiProtocolException('NDI PartyID and AgencyCode must be configured.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;
        $root = $document->createElementNS(self::NAMESPACE_URI, 'ew:inquiry_A2');
        $document->appendChild($root);

        $this->appendText($document, $root, 'DocumentID', $request->documentId);
        $this->appendText($document, $root, 'Variant', $request->variant);

        $reference = $document->createElement('CustomerReference');
        $root->appendChild($reference);
        $this->appendText($document, $reference, 'DocumentID', $request->customerReference);

        $buyerParty = $document->createElement('BuyerParty');
        $root->appendChild($buyerParty);
        $this->appendText($document, $buyerParty, 'PartyID', $partyId);
        $this->appendText($document, $buyerParty, 'AgencyCode', $agencyCode);

        foreach ($request->lines as $line) {
            $this->appendLine($document, $root, $line);
        }

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new NdiProtocolException('Unable to serialize NDI inquiry XML.');
        }

        return $xml;
    }

    public function parseQuote(string $xml): NdiQuoteResult
    {
        if (trim($xml) === '' || preg_match('/<!DOCTYPE/i', $xml) === 1) {
            throw new NdiProtocolException('NDI quote is empty or contains a forbidden document type.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $errors !== []) {
            throw new NdiProtocolException('NDI returned malformed quote XML.');
        }

        $root = $document->documentElement;
        if (! $root instanceof DOMElement || $root->localName !== 'quote_A2' || $root->namespaceURI !== self::NAMESPACE_URI) {
            throw new NdiProtocolException('NDI quote has an unexpected root element or namespace.');
        }

        $xpath = new DOMXPath($document);
        $documentId = $this->requiredText($xpath, $root, './*[local-name()="DocumentID"]', 'DocumentID');
        $errorCode = $this->requiredText($xpath, $root, './*[local-name()="ErrorHead"]/*[local-name()="ErrorCode"]', 'ErrorCode');
        $lines = [];

        foreach ($xpath->query('./*[local-name()="OrderLine"]', $root) ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $requested = $this->nonNegativeInteger($this->requiredText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="RequestedQuantity"]/*[local-name()="QuantityValue"]', 'RequestedQuantity'));
            $availableText = $this->optionalText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="ScheduleDetails"]/*[local-name()="AvailableQuantity"]/*[local-name()="QuantityValue"]');
            $lines[] = new NdiQuoteLine(
                lineId: $this->requiredText($xpath, $node, './*[local-name()="LineID"]', 'LineID'),
                sku: $this->requiredText(
                    $xpath,
                    $node,
                    './*[local-name()="OrderedArticle"]/*[local-name()="ArticleIdentification"]/*[local-name()="EANUCCArticleID" or local-name()="ManufacturersArticleID"]',
                    'article identifier',
                ),
                requestedQuantity: $requested,
                availableQuantity: $availableText === null ? 0 : $this->nonNegativeInteger($availableText),
                deliveryDate: $this->optionalText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="ScheduleDetails"]/*[local-name()="DeliveryDate"]'),
            );
        }

        if ($errorCode === '0' && $lines === []) {
            throw new NdiProtocolException('Successful NDI quote contains no order lines.');
        }

        return new NdiQuoteResult($documentId, $errorCode, $lines);
    }

    private function appendLine(DOMDocument $document, DOMElement $root, NdiInquiryLine $line): void
    {
        $orderLine = $document->createElement('OrderLine');
        $root->appendChild($orderLine);
        $this->appendText($document, $orderLine, 'LineID', $line->lineId);
        $article = $document->createElement('OrderedArticle');
        $orderLine->appendChild($article);
        $identification = $document->createElement('ArticleIdentification');
        $article->appendChild($identification);
        $this->appendText($document, $identification, 'EANUCCArticleID', $line->sku);
        $quantity = $document->createElement('RequestedQuantity');
        $article->appendChild($quantity);
        $this->appendText($document, $quantity, 'QuantityValue', (string) $line->quantity);
    }

    private function appendText(DOMDocument $document, DOMElement $parent, string $name, string $value): void
    {
        $parent->appendChild($document->createElement($name, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
    }

    private function requiredText(DOMXPath $xpath, DOMElement $context, string $query, string $field): string
    {
        $value = $this->optionalText($xpath, $context, $query);
        if ($value === null) {
            throw new NdiProtocolException("NDI quote is missing {$field}.");
        }

        return $value;
    }

    private function optionalText(DOMXPath $xpath, DOMElement $context, string $query): ?string
    {
        $node = $xpath->query($query, $context)?->item(0);
        if ($node === null) {
            return null;
        }

        $value = trim($node->textContent);

        return $value === '' ? null : $value;
    }

    private function nonNegativeInteger(string $value): int
    {
        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new NdiProtocolException('NDI quote contains an invalid quantity.');
        }

        return (int) $value;
    }
}
