<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Compare;

use Iperson1337\OpenDxpPricatBundle\Exception\PositionAlreadyProcessedException;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use Iperson1337\OpenDxpPricatBundle\Model\ProductFieldMapping;
use Iperson1337\OpenDxpPricatBundle\Service\Product\DataObjectFieldAccessor;
use Iperson1337\OpenDxpPricatBundle\Service\Product\ProductFinder;
use OpenDxp\Db;
use OpenDxp\Model\DataObject\Concrete;
use OpenDxp\Model\DataObject\PricatPosition;
use Psr\Log\LoggerInterface;

/**
 * "Accept" a position: copy every non-empty value of a writable mapping onto the product,
 * save the product, then mark the position processed + confirmed — in one transaction.
 */
final class PositionAcceptor
{
    public function __construct(
        private readonly PricatConfig $config,
        private readonly ProductFinder $productFinder,
        private readonly DataObjectFieldAccessor $accessor,
        private readonly ValueNormalizer $normalizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string>|null $onlyProductFields product fields to write (null = every writable mapping,
     *                                             [] = write nothing, just mark the position accepted)
     *
     * @return list<string> product fields that were written
     *
     * @throws PositionAlreadyProcessedException
     * @throws \Iperson1337\OpenDxpPricatBundle\Exception\ProductNotFoundException
     * @throws \InvalidArgumentException when $onlyProductFields names a field outside the writable mapping
     */
    public function accept(PricatPosition $position, ?array $onlyProductFields = null): array
    {
        if ($position->getProcessed() === true) {
            throw new PositionAlreadyProcessedException((int) $position->getId());
        }

        $mappings = $this->config->writableProductMapping($onlyProductFields);
        $product = $this->productFinder->findForPosition($position);
        $written = $this->applyMappings($mappings, $position, $product);

        $db = Db::get();
        $db->beginTransaction();
        try {
            if ($written !== []) {
                $product->save(['versionNote' => $this->config->productVersionNote]);
            }

            $position->setConfirmation(true);
            $position->setProcessed(true);
            $position->save(['versionNote' => 'PRICAT: accepted']);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            throw $e;
        }

        $this->logger->info('PRICAT position accepted', [
            'position_id' => $position->getId(),
            'product_id' => $product->getId(),
            'fields' => $written,
            'selection' => $onlyProductFields,
        ]);

        return $written;
    }

    /**
     * @param list<ProductFieldMapping> $mappings
     *
     * @return list<string>
     */
    private function applyMappings(array $mappings, PricatPosition $position, Concrete $product): array
    {
        $written = [];
        foreach ($mappings as $mapping) {
            if ($this->apply($mapping, $position, $product)) {
                $written[] = $mapping->productField;
            }
        }

        return $written;
    }

    private function apply(ProductFieldMapping $mapping, PricatPosition $position, Concrete $product): bool
    {
        $value = $this->accessor->read($position, $mapping->positionField);
        if ($this->normalizer->isEmpty($value)) {
            return false;
        }

        $current = $this->accessor->read($product, $mapping->productField);
        if ($this->normalizer->equals($value, $current)) {
            return false;
        }

        $this->accessor->write($product, $mapping->productField, $value);

        return true;
    }
}
