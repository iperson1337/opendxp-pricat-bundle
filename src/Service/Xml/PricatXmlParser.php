<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Xml;

use Iperson1337\OpenDxpPricatBundle\Exception\InvalidPricatXmlException;
use Iperson1337\OpenDxpPricatBundle\Model\ParsedPricat;

/**
 * Turns a PRICAT XML document into {@see ParsedPricat}.
 *
 * Expected shape (tag names are matched case-insensitively):
 *
 *   <PRICAT>
 *     <ACTION>9</ACTION> <NUMBER>..</NUMBER> ...          ← header: every child except CATALOGUE
 *     <CATALOGUE>
 *       <POSITION><POSITIONNUMBER>1</POSITIONNUMBER> ...</POSITION>
 *     </CATALOGUE>
 *   </PRICAT>
 */
final class PricatXmlParser
{
    public const string ROOT_TAG = 'pricat';
    public const string CATALOGUE_TAG = 'catalogue';
    public const string POSITION_TAG = 'position';

    public function parse(string $xml): ParsedPricat
    {
        $root = $this->loadRoot($xml);

        $header = [];
        $positions = [];

        foreach ($this->elementChildren($root) as $child) {
            if (mb_strtolower($child->localName) === self::CATALOGUE_TAG) {
                foreach ($this->elementChildren($child) as $position) {
                    if (mb_strtolower($position->localName) !== self::POSITION_TAG) {
                        continue;
                    }
                    $row = $this->leafValues($position);
                    if ($row !== []) {
                        $positions[] = $row;
                    }
                }

                continue;
            }

            $header[mb_strtolower($child->localName)] = trim($child->textContent);
        }

        $parsed = new ParsedPricat($header, $positions);
        if ($parsed->isEmpty()) {
            throw InvalidPricatXmlException::empty();
        }

        return $parsed;
    }

    private function loadRoot(string $xml): \DOMElement
    {
        if (trim($xml) === '') {
            throw InvalidPricatXmlException::malformed('empty document');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            // LIBXML_NONET: never resolve external entities / DTDs from the network for an uploaded file.
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOBLANKS);
            if (!$loaded) {
                $error = libxml_get_last_error();
                throw InvalidPricatXmlException::malformed($error ? trim($error->message) : 'unknown parser error');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            throw InvalidPricatXmlException::malformed('no root element');
        }
        if (mb_strtolower($root->localName) !== self::ROOT_TAG) {
            throw InvalidPricatXmlException::unexpectedRoot($root->localName);
        }

        return $root;
    }

    /**
     * @return array<string, string> lower-cased tag => trimmed text of every element child
     */
    private function leafValues(\DOMElement $element): array
    {
        $values = [];
        foreach ($this->elementChildren($element) as $child) {
            $values[mb_strtolower($child->localName)] = trim($child->textContent);
        }

        return $values;
    }

    /**
     * @return iterable<\DOMElement>
     */
    private function elementChildren(\DOMElement $element): iterable
    {
        foreach ($element->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                yield $node;
            }
        }
    }
}
