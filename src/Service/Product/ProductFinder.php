<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Product;

use Iperson1337\OpenDxpPricatBundle\Exception\ProductNotFoundException;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use OpenDxp\Model\DataObject\ClassDefinition;
use OpenDxp\Model\DataObject\Concrete;
use OpenDxp\Model\DataObject\Listing;
use OpenDxp\Model\DataObject\PricatPosition;

/**
 * Resolves the product a position refers to: `product.class` object whose `product.lookup_field`
 * equals the position's `product.position_lookup_field` value (barcode by default).
 */
final class ProductFinder
{
    public function __construct(private readonly PricatConfig $config)
    {
    }

    /**
     * @throws ProductNotFoundException
     */
    public function findForPosition(PricatPosition $position): Concrete
    {
        $value = $position->get($this->config->positionLookupField);
        $value = $value === null ? null : trim((string) $value);

        $product = $value === null || $value === '' ? null : $this->findByLookup($value);
        if ($product === null) {
            throw new ProductNotFoundException($this->config->productLookupField, $value);
        }

        return $product;
    }

    public function findByLookup(string $value): ?Concrete
    {
        $listing = $this->listing();
        $listing->setCondition(sprintf('`%s` = ?', $this->config->productLookupField), [$value]);
        $listing->setLimit(1);
        $listing->setOrderKey('id');
        $listing->setOrder('asc');

        $objects = $listing->load();
        $first = $objects[0] ?? null;

        return $first instanceof Concrete ? $first : null;
    }

    private function listing(): Listing
    {
        $class = ClassDefinition::getByName($this->config->productClass);
        if ($class === null) {
            throw new \LogicException(sprintf('DataObject class "%s" (iperson1337_opendxp_pricat.product.class) does not exist', $this->config->productClass));
        }

        $listingClass = '\\OpenDxp\\Model\\DataObject\\' . ucfirst($this->config->productClass) . '\\Listing';
        if (!class_exists($listingClass)) {
            throw new \LogicException(sprintf('Listing class "%s" is not generated', $listingClass));
        }

        /** @var Listing $listing */
        $listing = new $listingClass();
        $listing->setUnpublished($this->config->includeUnpublishedProducts);

        return $listing;
    }
}
