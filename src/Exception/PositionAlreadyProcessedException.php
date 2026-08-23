<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Exception;

final class PositionAlreadyProcessedException extends PricatException
{
    public function __construct(int $positionId)
    {
        parent::__construct(
            sprintf('PRICAT position #%d is already processed', $positionId),
            'plugin_pimcore_pricat_error_position_processed',
            ['%id%' => $positionId],
        );
    }
}
