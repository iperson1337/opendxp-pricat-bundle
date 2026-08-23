<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Exception;

final class DuplicatePricatException extends PricatException
{
    /**
     * @param array<string, scalar|null> $identity the duplicate-check field values that matched
     */
    public function __construct(array $identity, public readonly int $existingId)
    {
        $pairs = implode(', ', array_map(
            static fn (string $field, mixed $value): string => sprintf('%s=%s', $field, (string) $value),
            array_keys($identity),
            $identity,
        ));

        parent::__construct(
            sprintf('PRICAT already imported as object #%d (%s)', $existingId, $pairs),
            'plugin_pimcore_pricat_error_duplicate',
            ['%id%' => $existingId, '%identity%' => $pairs],
        );
    }
}
