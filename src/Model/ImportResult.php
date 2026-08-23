<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Model;

use OpenDxp\Model\DataObject\Pricat;

final readonly class ImportResult
{
    /**
     * @param list<string> $unmappedHeaderTags   XML tags of the header that matched no Pricat field
     * @param list<string> $unmappedPositionTags XML tags of positions that matched no PricatPosition field
     */
    public function __construct(
        public Pricat $pricat,
        public int $positionCount,
        public array $unmappedHeaderTags,
        public array $unmappedPositionTags,
    ) {
    }
}
