<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Model;

final readonly class PositionStats
{
    public function __construct(
        public int $total,
        public int $accepted,
        public int $declined,
    ) {
    }

    public function processed(): int
    {
        return $this->accepted + $this->declined;
    }

    public function pending(): int
    {
        return max(0, $this->total - $this->processed());
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'positionCount' => $this->total,
            'acceptedCount' => $this->accepted,
            'declinedCount' => $this->declined,
            'processedCount' => $this->processed(),
            'pendingCount' => $this->pending(),
        ];
    }
}
