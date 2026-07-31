<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Data\NdiOrderStatusRequest;
use App\Modules\NdiApi\Data\NdiOrderStatusResult;
use App\Modules\NdiApi\Data\NdiTrackingLine;
use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class NdiStatusXmlCodec
{
    public function buildRequest(NdiOrderStatusRequest $request, string $partyId): string
    {
        if ($partyId === '') {
            throw new NdiProtocolException('NDI PartyID must be configured.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElement('order_status_request');
        $document->appendChild($root);
        $this->appendText($document, $root, 'DocumentID', 'C1');
        $this->appendText($document, $root, 'PartyID', $partyId);
        $referenced = $this->appendElement($document, $root, 'ReferencedOrder');
        $reference = $this->appendElement($document, $referenced, 'OrderReference');
        $this->appendText($document, $reference, 'DocumentID', $request->externalOrderReference);

        return $document->saveXML() ?: throw new NdiProtocolException('Unable to serialize NDI status XML.');
    }

    public function parseResponse(string $xml): NdiOrderStatusResult
    {
        $document = NdiXml::load($xml, 'order_status_response_ndi', null);
        $root = $document->documentElement;
        $xpath = new DOMXPath($document);
        $erp = $xpath->query('./*[local-name()="ERP"]', $root)?->item(0);
        if (! $erp instanceof DOMElement) {
            throw new NdiProtocolException('NDI status response is missing ERP status.');
        }

        $transport = $xpath->query('./*[local-name()="TransportStatus"]', $root)?->item(0);
        $tracking = [];
        if ($transport instanceof DOMElement) {
            foreach ($xpath->query('./*[local-name()="OrderLine"]', $transport) ?: [] as $line) {
                if (! $line instanceof DOMElement) {
                    continue;
                }

                $tracking[] = new NdiTrackingLine(
                    trackingNumber: NdiXml::requiredText($xpath, $line, './*[local-name()="TrackingNumber"]', 'TrackingNumber'),
                    trackingUrl: NdiXml::optionalText($xpath, $line, './*[local-name()="TrackingLink"]'),
                    globalStatus: NdiXml::optionalText($xpath, $line, './*[local-name()="GlobalStatus"]'),
                    localStatus: NdiXml::optionalText($xpath, $line, './*[local-name()="LocalStatus"]'),
                    occurredAt: NdiXml::optionalText($xpath, $line, './*[local-name()="Date"]'),
                );
            }
        }

        return new NdiOrderStatusResult(
            externalOrderReference: NdiXml::requiredText($xpath, $erp, './*[local-name()="Reference"]', 'ERP Reference'),
            salesStatus: NdiXml::requiredText($xpath, $erp, './*[local-name()="SalesStatus"]', 'SalesStatus'),
            documentStatus: NdiXml::requiredText($xpath, $erp, './*[local-name()="DocumentStatus"]', 'DocumentStatus'),
            carrier: $transport instanceof DOMElement ? NdiXml::optionalText($xpath, $transport, './*[local-name()="Carrier"]') : null,
            carrierProduct: $transport instanceof DOMElement ? NdiXml::optionalText($xpath, $transport, './*[local-name()="CarrierProduct"]') : null,
            tracking: $tracking,
        );
    }

    private function appendElement(DOMDocument $document, DOMElement $parent, string $name): DOMElement
    {
        $element = $document->createElement($name);
        $parent->appendChild($element);

        return $element;
    }

    private function appendText(DOMDocument $document, DOMElement $parent, string $name, string $value): void
    {
        $element = $this->appendElement($document, $parent, $name);
        $element->appendChild($document->createTextNode($value));
    }
}
