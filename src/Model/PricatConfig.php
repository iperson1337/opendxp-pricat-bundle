<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Model;

/**
 * Typed view of the processed `iperson1337_opendxp_pricat` configuration.
 * Built once by the bundle extension and injected into every service that needs a setting.
 */
final readonly class PricatConfig
{
    /**
     * @param list<string>                 $duplicateCheckFields
     * @param array<string, string>        $pricatFieldAliases    lower-cased XML tag => field name
     * @param array<string, string>        $positionFieldAliases  lower-cased XML tag => field name
     * @param list<string>                 $ignoredTags           lower-cased
     * @param list<ProductFieldMapping>    $productMapping
     */
    public function __construct(
        public string $permission,
        public string $folderPath,
        public string $dateFormat,
        public array $duplicateCheckFields,
        public array $pricatFieldAliases,
        public array $positionFieldAliases,
        public array $ignoredTags,
        public string $productClass,
        public string $productLookupField,
        public string $positionLookupField,
        public bool $includeUnpublishedProducts,
        public string $productVersionNote,
        public array $productMapping,
        public int $pageSize,
    ) {
    }

    /**
     * @param array<string, mixed> $config processed configuration (see Configuration)
     */
    public static function fromArray(array $config): self
    {
        $lowerKeys = static fn (array $map): array => array_combine(
            array_map(static fn (string $tag): string => mb_strtolower($tag), array_keys($map)),
            array_values($map),
        );

        return new self(
            permission: $config['permission'],
            folderPath: $config['folder_path'],
            dateFormat: $config['date_format'],
            duplicateCheckFields: array_values($config['duplicate_check_fields']),
            pricatFieldAliases: $lowerKeys($config['xml']['pricat_field_aliases']),
            positionFieldAliases: $lowerKeys($config['xml']['position_field_aliases']),
            ignoredTags: array_map(static fn (string $tag): string => mb_strtolower($tag), $config['xml']['ignored_tags']),
            productClass: $config['product']['class'],
            productLookupField: $config['product']['lookup_field'],
            positionLookupField: $config['product']['position_lookup_field'],
            includeUnpublishedProducts: $config['product']['include_unpublished'],
            productVersionNote: $config['product']['version_note'],
            productMapping: array_map(ProductFieldMapping::fromArray(...), array_values($config['product']['mapping'])),
            pageSize: $config['list']['page_size'],
        );
    }

    /**
     * Writable mappings, optionally narrowed to the given product fields (selective accept).
     *
     * @param list<string>|null $onlyProductFields null = every writable mapping; [] = none
     *
     * @return list<ProductFieldMapping>
     *
     * @throws \InvalidArgumentException when a requested field is unknown or not writable
     */
    public function writableProductMapping(?array $onlyProductFields = null): array
    {
        $writable = array_values(array_filter(
            $this->productMapping,
            static fn (ProductFieldMapping $mapping): bool => $mapping->writable,
        ));
        if ($onlyProductFields === null) {
            return $writable;
        }

        $byField = [];
        foreach ($writable as $mapping) {
            $byField[$mapping->productField] = $mapping;
        }

        $unknown = array_values(array_diff(array_unique($onlyProductFields), array_keys($byField)));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Product field(s) not writable through the PRICAT mapping: %s',
                implode(', ', $unknown),
            ));
        }

        return array_values(array_filter(
            $writable,
            static fn (ProductFieldMapping $mapping): bool => \in_array($mapping->productField, $onlyProductFields, true),
        ));
    }
}
