<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Exception;

final class InvalidPricatXmlException extends PricatException
{
    public static function malformed(string $detail, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('PRICAT XML is not well-formed: %s', $detail),
            'plugin_pimcore_pricat_error_invalid_xml',
            ['%detail%' => $detail],
            $previous,
        );
    }

    public static function unexpectedRoot(string $rootName): self
    {
        return new self(
            sprintf('Expected <PRICAT> root element, got <%s>', $rootName),
            'plugin_pimcore_pricat_error_unexpected_root',
            ['%root%' => $rootName],
        );
    }

    public static function empty(): self
    {
        return new self(
            'PRICAT contains no header fields or no positions',
            'plugin_pimcore_pricat_error_empty',
        );
    }
}
