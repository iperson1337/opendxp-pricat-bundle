<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Model;

/**
 * One row of the compare window: which position field is held against which product field.
 */
final readonly class ProductFieldMapping
{
    /** Read / write the product field itself; the value type is taken from the class definition. */
    public const string ACCESSOR_FIELD = 'field';

    /** Read the `name` of the object a relation field points to. Read-only. */
    public const string ACCESSOR_RELATION_NAME = 'relation_name';

    public const array ACCESSORS = [self::ACCESSOR_FIELD, self::ACCESSOR_RELATION_NAME];

    public function __construct(
        public string $positionField,
        public string $productField,
        public string $accessor = self::ACCESSOR_FIELD,
        public bool $writable = true,
    ) {
        if (!\in_array($accessor, self::ACCESSORS, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown accessor "%s"', $accessor));
        }
        if ($writable && $accessor === self::ACCESSOR_RELATION_NAME) {
            throw new \InvalidArgumentException(sprintf(
                'Mapping %s → %s: accessor "%s" is read-only',
                $positionField,
                $productField,
                $accessor,
            ));
        }
    }

    /**
     * @param array{position: string, product: string, accessor?: string, writable?: bool} $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            $row['position'],
            $row['product'],
            $row['accessor'] ?? self::ACCESSOR_FIELD,
            $row['writable'] ?? true,
        );
    }

    public function isRelationName(): bool
    {
        return $this->accessor === self::ACCESSOR_RELATION_NAME;
    }
}
