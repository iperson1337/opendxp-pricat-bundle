<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Product;

use Iperson1337\OpenDxpPricatBundle\Exception\UnsupportedFieldException;
use Iperson1337\OpenDxpPricatBundle\Model\ProductFieldMapping;
use Iperson1337\OpenDxpPricatBundle\Service\Import\FieldValueCaster;
use OpenDxp\Model\DataObject\ClassDefinition\Data;
use OpenDxp\Model\DataObject\Concrete;
use OpenDxp\Model\DataObject\Data\QuantityValue;
use OpenDxp\Model\Element\ElementInterface;

/**
 * Reads and writes DataObject fields by name, driven by the class definition instead of
 * per-field getter/setter code. Covers what the PRICAT reconciliation needs:
 *
 *  - plain fields (input, textarea, numeric, checkbox, booleanSelect, localized variants): scalar in/out
 *  - quantityValue: the numeric value; on write an existing unit is kept, a missing one is created with the field's default unit
 *  - *ToOneRelation with accessor `relation_name`: the related object's name (read-only)
 */
final class DataObjectFieldAccessor
{
    public function __construct(private readonly FieldValueCaster $caster)
    {
    }

    public function definition(Concrete $object, string $field): Data
    {
        $definition = $object->getClass()->getFieldDefinition($field);
        if ($definition === null) {
            throw UnsupportedFieldException::missing((string) $object->getClass()->getName(), $field);
        }

        return $definition;
    }

    /**
     * @return int|float|string|bool|null scalar view of the field (quantityValue → its value, relation → name)
     */
    public function read(Concrete $object, string $field, string $accessor = ProductFieldMapping::ACCESSOR_FIELD): int|float|string|bool|null
    {
        $definition = $this->definition($object, $field);
        $value = $object->get($field);

        if ($accessor === ProductFieldMapping::ACCESSOR_RELATION_NAME) {
            if (!$definition instanceof Data\ManyToOneRelation) {
                throw UnsupportedFieldException::notARelation((string) $object->getClass()->getName(), $field, $definition->getFieldType());
            }

            return $this->nameOf($value);
        }

        if ($value instanceof QuantityValue) {
            return $value->getValue();
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value instanceof ElementInterface) {
            return $this->nameOf($value);
        }
        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }

    /**
     * Writes a raw (string-able) value, casting it to the type the setter expects.
     *
     * @throws UnsupportedFieldException when the field type is not writable by this bundle
     */
    public function write(Concrete $object, string $field, int|float|string|bool|null $value): void
    {
        $definition = $this->definition($object, $field);
        $className = (string) $object->getClass()->getName();

        if ($definition instanceof Data\QuantityValue) {
            $this->writeQuantityValue($object, $definition, $value);

            return;
        }

        if ($definition instanceof Data\AbstractQuantityValue || $definition->isRelationType() || $definition instanceof Data\Localizedfields) {
            throw UnsupportedFieldException::notWritable($className, $field, $definition->getFieldType());
        }

        $raw = \is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string) $value);
        $object->set($field, $this->caster->cast($definition, $raw));
    }

    public function isWritable(Concrete $object, string $field): bool
    {
        $definition = $this->definition($object, $field);

        return $definition instanceof Data\QuantityValue
            || (!$definition instanceof Data\AbstractQuantityValue && !$definition->isRelationType() && !$definition instanceof Data\Localizedfields);
    }

    private function writeQuantityValue(Concrete $object, Data\QuantityValue $definition, int|float|string|bool|null $value): void
    {
        $numeric = $value === null || $value === '' ? null : (float) $value;
        if ($numeric === null) {
            $object->set($definition->getName(), null);

            return;
        }

        $current = $object->get($definition->getName());
        if ($current instanceof QuantityValue) {
            $current->setValue($numeric);
            $object->set($definition->getName(), $current);

            return;
        }

        $object->set($definition->getName(), new QuantityValue($numeric, $definition->getDefaultUnit()));
    }

    private function nameOf(mixed $element): ?string
    {
        if ($element === null) {
            return null;
        }
        if (\is_object($element) && method_exists($element, 'getName')) {
            $name = $element->getName();

            return \is_scalar($name) ? (string) $name : null;
        }
        if ($element instanceof ElementInterface) {
            return $element->getKey();
        }

        return null;
    }
}
