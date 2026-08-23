<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Exception;

final class ProductNotFoundException extends PricatException
{
    public function __construct(string $lookupField, ?string $lookupValue)
    {
        parent::__construct(
            sprintf('No product with %s = "%s"', $lookupField, (string) $lookupValue),
            'plugin_pimcore_pricat_error_product_not_found',
            ['%field%' => $lookupField, '%value%' => (string) $lookupValue],
        );
    }
}
