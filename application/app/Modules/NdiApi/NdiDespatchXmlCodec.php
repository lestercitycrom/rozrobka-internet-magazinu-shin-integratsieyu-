<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Data\NdiDespatchDocument;
use App\Modules\NdiApi\Data\NdiDespatchRequest;
use App\Modules\NdiApi\Data\NdiDespatchResult;
use App\Modules\NdiApi\Data\NdiTrackingLine;
use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class NdiDespatchXmlCodec
{
    public const NAMESPACE_URI = 'http://www.reifen.net';

    public function buildRequest(NdiDespatchRequest $request, string $partyId, string $agencyCode): string
    {
        if ($partyId === '' || $agencyCode === '') {
            throw new NdiProtocolException('NDI PartyID and AgencyCode must be configured.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS(self::NAMESPACE_URI, 'desadv_request');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $document->appendChild($root);
        $this->appendText($document, $root, 'DocumentID', 'C1');
        $this->appendText($document, $root, 'Variant', '0');
        $references = $this->appendElement($document, $root, 'References');
        $this->appendText($document, $references, 'ReferenceTypeCode', 'VN');
        $this->appendText($document, $references, 'DocumentID', $request->externalOrderReference);
        $buyer = $this->appendElement($document, $root, 'BuyerParty');
        $this->appendText($document, $buyer, 'PartyID', $partyId);
        $this->appendText($document, $buyer, 'AgencyCode', $agencyCode);

        return $document->saveXML() ?: throw new NdiProtocolException('Unable to serialize NDI DesAdv XML.');
    }

    public function parseResponse(string $xml): NdiDespatchResult
    {
        $document = NdiXml::load($xml, 'desadv_list_pull', self::NAMESPACE_URI);
        $root = $document->documentElement;
        $xpath = new DOMXPath($document);
        $documents = [];

        foreach ($xpath->query('./*[local-name()="desadv"]', $root) ?: [] as $despatch) {
            if (! $despatch instanceof DOMElement) {
                continue;
            }

            $tracking = [];
            foreach ($xpath->query('./*[local-name()="LineLevel"]', $despatch) ?: [] as $line) {
                if (! $line instanceof DOMElement) {
                    continue;
                }

                $tracing = $xpath->query('./*[local-name()="OrderedArticle"]/*[local-name()="TracingInformation"]', $line)?->item(0);
                if (! $tracing instanceof DOMElement) {
                    continue;
                }

                $tracking[] = new NdiTrackingLine(
                    trackingNumber: NdiXml::requiredText($xpath, $tracing, './*[local-name()="TracingID"]', 'TracingID'),
                    trackingUrl: NdiXml::optionalText($xpath, $tracing, './*[local-name()="TracingURI"]'),
                    globalStatus: null,
                    localStatus: null,
                    occurredAt: null,
                );
            }

            $documents[] = new NdiDespatchDocument(
                documentNumber: NdiXml::requiredText($xpath, $despatch, './*[local-name()="DocumentNumber"]', 'despatch DocumentNumber'),
                arrivalDate: NdiXml::optionalText($xpath, $despatch, './*[local-name()="ArrivalDate"]'),
                tracking: $tracking,
            );
        }

        return new NdiDespatchResult(
            errorCode: NdiXml::requiredText($xpath, $root, './*[local-name()="ErrorHead"]/*[local-name()="ErrorCode"]', 'ErrorCode'),
            documents: $documents,
        );
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
