<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Exceptions\NdiProtocolException;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class NdiXml
{
    public static function load(string $xml, string $expectedRoot, ?string $expectedNamespace): DOMDocument
    {
        if (trim($xml) === '' || preg_match('/<!DOCTYPE/i', $xml) === 1) {
            throw new NdiProtocolException('NDI XML is empty or contains a forbidden document type.');
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
            throw new NdiProtocolException('NDI returned malformed XML.');
        }

        $root = $document->documentElement;
        if (! $root instanceof DOMElement || $root->localName !== $expectedRoot || ($root->namespaceURI ?: null) !== $expectedNamespace) {
            throw new NdiProtocolException('NDI XML has an unexpected root element or namespace.');
        }

        return $document;
    }

    public static function requiredText(DOMXPath $xpath, DOMElement $context, string $query, string $field): string
    {
        $value = self::optionalText($xpath, $context, $query);
        if ($value === null) {
            throw new NdiProtocolException("NDI XML is missing {$field}.");
        }

        return $value;
    }

    public static function optionalText(DOMXPath $xpath, DOMElement $context, string $query): ?string
    {
        $node = $xpath->query($query, $context)?->item(0);
        if ($node === null) {
            return null;
        }

        $value = trim($node->textContent);

        return $value === '' ? null : $value;
    }

    public static function nonNegativeInteger(?string $value, string $field): int
    {
        if ($value === null) {
            return 0;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new NdiProtocolException("NDI XML contains an invalid {$field}.");
        }

        return (int) $value;
    }
}
