<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Compare;

use Iperson1337\OpenDxpPricatBundle\Model\CompareRow;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use Iperson1337\OpenDxpPricatBundle\Model\ProductFieldMapping;
use Iperson1337\OpenDxpPricatBundle\Service\Product\DataObjectFieldAccessor;
use OpenDxp\Model\DataObject\Concrete;
use OpenDxp\Model\DataObject\PricatPosition;

/**
 * Builds the compare table for a position against its product, one row per configured mapping.
 */
final class PositionProductComparator
{
    public function __construct(
        private readonly PricatConfig $config,
        private readonly DataObjectFieldAccessor $accessor,
        private readonly ValueNormalizer $normalizer,
        private readonly FieldLabelProvider $labels,
    ) {
    }

    /**
     * @return list<CompareRow>
     */
    public function compare(PricatPosition $position, Concrete $product): array
    {
        return array_map(
            fn (ProductFieldMapping $mapping): CompareRow => $this->row($mapping, $position, $product),
            $this->config->productMapping,
        );
    }

    private function row(ProductFieldMapping $mapping, PricatPosition $position, Concrete $product): CompareRow
    {
        $positionValue = $this->accessor->read($position, $mapping->positionField);
        $productValue = $this->accessor->read($product, $mapping->productField, $mapping->accessor);

        return new CompareRow(
            positionField: $mapping->positionField,
            productField: $mapping->productField,
            label: $this->labels->label($position, $mapping->positionField),
            positionValue: $this->display($positionValue),
            productValue: $this->display($productValue),
            isEqual: $this->normalizer->equals($positionValue, $productValue),
            isProductEmpty: $this->normalizer->isEmpty($productValue),
            isPositionEmpty: $this->normalizer->isEmpty($positionValue),
            writable: $mapping->writable,
        );
    }

    private function display(int|float|string|bool|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
