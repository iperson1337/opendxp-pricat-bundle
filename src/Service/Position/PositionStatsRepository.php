<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Position;

use Iperson1337\OpenDxpPricatBundle\Model\PositionStats;
use OpenDxp\Db;
use OpenDxp\Model\DataObject\ClassDefinition;

/**
 * Per-pricat position counters (total / accepted / declined / pending) in one grouped read query.
 * Read-only SQL on the PricatPosition query table; the table name is derived from the class id.
 */
final class PositionStatsRepository
{
    private ?string $queryTable = null;

    /**
     * @param list<int> $pricatIds
     *
     * @return array<int, PositionStats> keyed by pricat id; pricats without positions get zero stats
     */
    public function forPricats(array $pricatIds): array
    {
        $stats = [];
        foreach ($pricatIds as $id) {
            $stats[$id] = new PositionStats(0, 0, 0);
        }
        if ($pricatIds === []) {
            return $stats;
        }

        $rows = Db::get()->fetchAllAssociative(
            sprintf(
                'SELECT o.parentId AS pricatId,
                        COUNT(*) AS total,
                        SUM(q.processed = 1 AND q.confirmation = 1) AS accepted,
                        SUM(q.processed = 1 AND (q.confirmation IS NULL OR q.confirmation <> 1)) AS declined
                   FROM %s q
                   JOIN objects o ON o.id = q.oo_id
                  WHERE o.parentId IN (?)
                  GROUP BY o.parentId',
                $this->queryTable(),
            ),
            [$pricatIds],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER],
        );

        foreach ($rows as $row) {
            $stats[(int) $row['pricatId']] = new PositionStats(
                (int) $row['total'],
                (int) $row['accepted'],
                (int) $row['declined'],
            );
        }

        return $stats;
    }

    private function queryTable(): string
    {
        if ($this->queryTable === null) {
            $class = ClassDefinition::getByName('PricatPosition');
            if ($class === null) {
                throw new \LogicException('DataObject class "PricatPosition" does not exist');
            }
            $this->queryTable = 'object_query_' . $class->getId();
        }

        return $this->queryTable;
    }
}
