<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Data\NdiC1OrderLine;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiC1OrderResult;
use App\Modules\NdiApi\Data\NdiC1OrderResultLine;
use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class NdiC1XmlCodec
{
    public const NAMESPACE_URI = 'http://www.reifen.net';

    public function buildOrder(NdiC1OrderRequest $request, string $partyId, string $agencyCode): string
    {
        if ($partyId === '' || $agencyCode === '') {
            throw new NdiProtocolException('NDI PartyID and AgencyCode must be configured.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS(self::NAMESPACE_URI, 'order');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $document->appendChild($root);

        $this->appendText($document, $root, 'DocumentID', 'C1');
        $this->appendText($document, $root, 'TransportPriority', $request->transportPriority);
        if ($request->deliveryRemarks !== null && $request->deliveryRemarks !== '') {
            $this->appendText($document, $root, 'DeliveryRemarks', $request->deliveryRemarks);
        }

        $reference = $this->appendElement($document, $root, 'CustomerReference');
        $this->appendText($document, $reference, 'DocumentID', $request->customerReference);

        $buyer = $this->appendElement($document, $root, 'BuyerParty');
        $this->appendText($document, $buyer, 'PartyID', $partyId);
        $this->appendText($document, $buyer, 'AgencyCode', $agencyCode);

        foreach ($request->lines as $line) {
            $this->appendLine($document, $root, $line);
        }

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new NdiProtocolException('Unable to serialize NDI C1 order XML.');
        }

        return $xml;
    }

    public function parseOrderResponse(string $xml): NdiC1OrderResult
    {
        $document = NdiXml::load($xml, 'order_response', self::NAMESPACE_URI);
        $root = $document->documentElement;
        $xpath = new DOMXPath($document);
        $lines = [];

        foreach ($xpath->query('./*[local-name()="OrderLine"]', $root) ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $lines[] = new NdiC1OrderResultLine(
                lineId: NdiXml::requiredText($xpath, $node, './*[local-name()="LineID"]', 'LineID'),
                buyersArticleId: NdiXml::requiredText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="ArticleIdentification"]/*[local-name()="BuyersArticleID"]', 'BuyersArticleID'),
                errorCode: NdiXml::requiredText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="Error"]/*[local-name()="ErrorCode"]', 'line ErrorCode'),
                externalOrderReference: NdiXml::optionalText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="OrderReference"]/*[local-name()="DocumentID"]'),
                confirmedQuantity: NdiXml::nonNegativeInteger(NdiXml::optionalText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="ScheduleDetails"]/*[local-name()="ConfirmedQuantity"]/*[local-name()="QuantityValue"]'), 'confirmed quantity'),
                deliveryDate: NdiXml::optionalText($xpath, $node, './*[local-name()="OrderedArticle"]/*[local-name()="ScheduleDetails"]/*[local-name()="DeliveryDate"]'),
            );
        }

        $result = new NdiC1OrderResult(
            documentId: NdiXml::requiredText($xpath, $root, './*[local-name()="DocumentID"]', 'DocumentID'),
            customerReference: NdiXml::requiredText($xpath, $root, './*[local-name()="CustomerReference"]/*[local-name()="DocumentID"]', 'CustomerReference'),
            errorCode: NdiXml::requiredText($xpath, $root, './*[local-name()="ErrorHead"]/*[local-name()="ErrorCode"]', 'ErrorCode'),
            lines: $lines,
        );

        if ($result->errorCode === '0' && $result->lines === []) {
            throw new NdiProtocolException('Successful NDI C1 response contains no order lines.');
        }

        return $result;
    }

    private function appendLine(DOMDocument $document, DOMElement $root, NdiC1OrderLine $line): void
    {
        $orderLine = $this->appendElement($document, $root, 'OrderLine');
        $this->appendText($document, $orderLine, 'LineID', $line->lineId);
        $article = $this->appendElement($document, $orderLine, 'OrderedArticle');
        $identification = $this->appendElement($document, $article, 'ArticleIdentification');
        $this->appendText($document, $identification, 'BuyersArticleID', $line->buyersArticleId);
        $quantity = $this->appendElement($document, $article, 'RequestedQuantity');
        $this->appendText($document, $quantity, 'QuantityValue', (string) $line->quantity);
    }

    private function appendElement(DOMDocument $document, DOMElement $parent, string $name): DOMElement
    {
        $element = $document->createElement($name);
        if ($parent->namespaceURI === self::NAMESPACE_URI) {
            $element->setAttribute('xmlns', '');
        }
        $parent->appendChild($element);

        return $element;
    }

    private function appendText(DOMDocument $document, DOMElement $parent, string $name, string $value): void
    {
        $element = $this->appendElement($document, $parent, $name);
        $element->appendChild($document->createTextNode($value));
    }
}
