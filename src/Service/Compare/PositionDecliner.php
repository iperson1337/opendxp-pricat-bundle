<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Compare;

use Iperson1337\OpenDxpPricatBundle\Exception\PositionAlreadyProcessedException;
use OpenDxp\Model\DataObject\PricatPosition;
use Psr\Log\LoggerInterface;

/**
 * "Decline" a position: store the reviewer's comment and mark it processed + not confirmed.
 */
final class PositionDecliner
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws PositionAlreadyProcessedException
     * @throws \InvalidArgumentException when the comment is blank
     */
    public function decline(PricatPosition $position, string $comment): void
    {
        if ($position->getProcessed() === true) {
            throw new PositionAlreadyProcessedException((int) $position->getId());
        }
        $comment = trim($comment);
        if ($comment === '') {
            throw new \InvalidArgumentException('Rejection comment must not be blank');
        }

        $position->setRejectionComment($comment);
        $position->setConfirmation(false);
        $position->setProcessed(true);
        $position->save(['versionNote' => 'PRICAT: declined']);

        $this->logger->info('PRICAT position declined', [
            'position_id' => $position->getId(),
            'comment' => $comment,
        ]);
    }
}
