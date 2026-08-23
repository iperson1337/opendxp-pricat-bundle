<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Import;

use Carbon\Carbon;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use OpenDxp\Model\DataObject\Pricat;

/**
 * Looks for an already imported Pricat with the same identity (configurable field set).
 */
final class PricatDuplicateFinder
{
    public function __construct(private readonly PricatConfig $config)
    {
    }

    /**
     * @return array<string, scalar|null> field => comparable value (dates as Y-m-d); empty values omitted
     */
    public function identityOf(Pricat $pricat): array
    {
        $identity = [];
        foreach ($this->config->duplicateCheckFields as $field) {
            $value = $pricat->get($field);
            if ($value instanceof \DateTimeInterface) {
                $value = Carbon::instance($value)->format('Y-m-d');
            }
            if ($value === null || $value === '') {
                continue;
            }
            $identity[$field] = \is_bool($value) ? (int) $value : $value;
        }

        return $identity;
    }

    /**
     * @param array<string, scalar|null> $identity
     *
     * @return int|null id of the existing object
     */
    public function findExisting(array $identity, ?int $excludeId = null): ?int
    {
        if ($identity === []) {
            return null;
        }

        $listing = new Pricat\Listing();
        $listing->setUnpublished(true);
        $listing->setLimit(1);

        $conditions = [];
        $params = [];
        foreach ($identity as $field => $value) {
            $conditions[] = sprintf('`%s` = ?', $field);
            $params[] = $value;
        }
        if ($excludeId !== null) {
            $conditions[] = 'id <> ?';
            $params[] = $excludeId;
        }
        $listing->setCondition(implode(' AND ', $conditions), $params);

        $existing = $listing->load();

        return $existing === [] ? null : $existing[0]->getId();
    }
}
