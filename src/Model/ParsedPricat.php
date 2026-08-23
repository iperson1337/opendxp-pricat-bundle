<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Model;

/**
 * PRICAT XML reduced to plain arrays: header tag => text, and one tag => text map per <POSITION>.
 * Tag names are lower-cased, text values trimmed. No DataObject knowledge here.
 */
final readonly class ParsedPricat
{
    /**
     * @param array<string, string>       $header
     * @param list<array<string, string>> $positions
     */
    public function __construct(
        public array $header,
        public array $positions,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->header === [] || $this->positions === [];
    }

    public function headerValue(string $tag): ?string
    {
        return $this->header[mb_strtolower($tag)] ?? null;
    }
}
