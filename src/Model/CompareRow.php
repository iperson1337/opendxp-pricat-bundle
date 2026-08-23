<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Model;

/**
 * One line of the compare window. Serialised as-is to the admin UI.
 */
final readonly class CompareRow
{
    public function __construct(
        public string $positionField,
        public string $productField,
        public string $label,
        public ?string $positionValue,
        public ?string $productValue,
        public bool $isEqual,
        public bool $isProductEmpty,
        public bool $isPositionEmpty,
        public bool $writable,
    ) {
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'positionField' => $this->positionField,
            'productField' => $this->productField,
            'label' => $this->label,
            'positionValue' => $this->positionValue,
            'productValue' => $this->productValue,
            'isEqual' => $this->isEqual,
            'isProductEmpty' => $this->isProductEmpty,
            'isPositionEmpty' => $this->isPositionEmpty,
            'writable' => $this->writable,
        ];
    }
}
