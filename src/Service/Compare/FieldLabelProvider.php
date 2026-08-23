<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Compare;

use OpenDxp\Model\DataObject\Concrete;

/**
 * Human-readable title of a field: the class definition title, falling back to the field name.
 */
final class FieldLabelProvider
{
    public function label(Concrete $object, string $field): string
    {
        $definition = $object->getClass()->getFieldDefinition($field);
        $title = $definition?->getTitle();

        return $title === null || trim($title) === '' ? $field : $title;
    }
}
