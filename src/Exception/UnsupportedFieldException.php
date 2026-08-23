<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Exception;

/**
 * A configured field does not exist on the class or its type cannot be read/written by the bundle.
 * This is a configuration error, not a runtime condition — hence LogicException.
 */
final class UnsupportedFieldException extends \LogicException
{
    public static function missing(string $className, string $field): self
    {
        return new self(sprintf('Class "%s" has no field "%s" (check iperson1337_opendxp_pricat configuration)', $className, $field));
    }

    public static function notWritable(string $className, string $field, string $fieldType): self
    {
        return new self(sprintf('Field "%s.%s" of type "%s" cannot be written by the PRICAT bundle; set writable: false', $className, $field, $fieldType));
    }

    public static function notARelation(string $className, string $field, string $fieldType): self
    {
        return new self(sprintf('Field "%s.%s" of type "%s" is not a relation; accessor "relation_name" needs a *ToOneRelation', $className, $field, $fieldType));
    }
}
