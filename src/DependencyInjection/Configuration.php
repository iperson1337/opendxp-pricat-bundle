<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\DependencyInjection;

use Iperson1337\OpenDxpPricatBundle\Model\ProductFieldMapping;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * `iperson1337_opendxp_pricat` configuration tree.
 *
 * Every value has a default that reproduces the original hard-coded behaviour of the bundle,
 * so an application may ship an empty `config/packages/iperson1337_opendxp_pricat.yaml`
 * and only override what differs (folder, product class, field mapping, ...).
 */
final class Configuration implements ConfigurationInterface
{
    public const string ROOT = 'iperson1337_opendxp_pricat';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT);
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();

        $root
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('permission')
                    ->info('Admin user permission key required to see the menu entry and call the endpoints. Created in users_permission_definitions by the bundle installer (opendxp:bundle:install OpenDxpPricatBundle).')
                    ->cannotBeEmpty()
                    ->defaultValue('pricat_bundle')
                ->end()
                ->scalarNode('folder_path')
                    ->info('DataObject folder the imported Pricat objects are created in (created on demand).')
                    ->cannotBeEmpty()
                    ->defaultValue('/Прикаты')
                ->end()
                ->scalarNode('date_format')
                    ->info('Format of <DATE>/<DATEFROM>/<DATETO> values in the XML (Carbon/DateTime format). Values that do not match are parsed leniently, and dropped when that fails too.')
                    ->cannotBeEmpty()
                    ->defaultValue('Y-m-d')
                ->end()
                ->arrayNode('duplicate_check_fields')
                    ->info('Pricat fields that identify an already imported price list; a second import with the same values is rejected. Empty header values are skipped from the check.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['number', 'action', 'date', 'docFlowId'])
                ->end()
                ->append($this->xmlNode())
                ->append($this->productNode())
                ->append($this->listNode())
            ->end();

        return $treeBuilder;
    }

    private function xmlNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('xml'))->getRootNode();
        /** @var ArrayNodeDefinition $node */
        $node
            ->addDefaultsIfNotSet()
            ->info('How XML tags are mapped onto DataObject fields. By default a tag is matched to the field with the same name, case-insensitively (<DOCFLOWID> → docFlowId). Aliases cover tags whose name differs from the field.')
            ->children()
                ->arrayNode('pricat_field_aliases')
                    ->info('XML tag (case-insensitive) => Pricat field name.')
                    ->useAttributeAsKey('tag')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('position_field_aliases')
                    ->info('XML tag (case-insensitive) => PricatPosition field name.')
                    ->useAttributeAsKey('tag')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        'POSITIONNUMBER' => 'positonNumber',
                        'DAYSUNTILSPOIL' => 'bestBeforDate',
                    ])
                ->end()
                ->arrayNode('ignored_tags')
                    ->info('Tags (case-insensitive) that are known but intentionally not stored; they are not reported as unmapped.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['CURRENCY'])
                ->end()
            ->end();

        return $node;
    }

    private function productNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('product'))->getRootNode();
        /** @var ArrayNodeDefinition $node */
        $node
            ->addDefaultsIfNotSet()
            ->info('Which DataObject a position is reconciled against and which fields are compared / written on accept.')
            ->children()
                ->scalarNode('class')
                    ->info('DataObject class name (without namespace).')
                    ->cannotBeEmpty()
                    ->defaultValue('Product')
                ->end()
                ->scalarNode('lookup_field')
                    ->info('Product field matched against the position lookup value (position.product = barcode).')
                    ->cannotBeEmpty()
                    ->defaultValue('barcode')
                ->end()
                ->scalarNode('position_lookup_field')
                    ->info('PricatPosition field holding the product identifier.')
                    ->cannotBeEmpty()
                    ->defaultValue('product')
                ->end()
                ->booleanNode('include_unpublished')
                    ->info('Also match unpublished products.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('version_note')
                    ->info('Version note written on the product when a position is accepted.')
                    ->defaultValue('PRICAT: позиция принята')
                ->end()
                ->arrayNode('mapping')
                    ->info('Position field ↔ product field pairs shown in the compare window. `writable: false` pairs are compare-only (never written on accept) — use it for fields that point to shared reference objects (brand, country, supplier).')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('position')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('product')->isRequired()->cannotBeEmpty()->end()
                            ->enumNode('accessor')
                                ->values(ProductFieldMapping::ACCESSORS)
                                ->defaultValue(ProductFieldMapping::ACCESSOR_FIELD)
                                ->info('`field` reads/writes the product field itself (type taken from the class definition); `relation_name` reads the name of the related object (read-only).')
                            ->end()
                            ->booleanNode('writable')->defaultTrue()->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static fn (array $m): bool => $m['writable'] && $m['accessor'] === ProductFieldMapping::ACCESSOR_RELATION_NAME)
                            ->thenInvalid('Mapping "%s": accessor "relation_name" is read-only, set writable: false.')
                        ->end()
                    ->end()
                    ->defaultValue(self::defaultProductMapping())
                ->end()
            ->end();

        return $node;
    }

    private function listNode(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('list'))->getRootNode();
        /** @var ArrayNodeDefinition $node */
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('page_size')
                    ->info('Rows per page in the pricat / positions grids.')
                    ->min(1)
                    ->defaultValue(25)
                ->end()
            ->end();

        return $node;
    }

    /**
     * Mirrors the pairs the bundle compared and wrote before the mapping became configurable.
     *
     * @return list<array{position: string, product: string, accessor: string, writable: bool}>
     */
    public static function defaultProductMapping(): array
    {
        $field = static fn (string $position, string $product, bool $writable = true): array => [
            'position' => $position,
            'product' => $product,
            'accessor' => ProductFieldMapping::ACCESSOR_FIELD,
            'writable' => $writable,
        ];
        $relationName = static fn (string $position, string $product): array => [
            'position' => $position,
            'product' => $product,
            'accessor' => ProductFieldMapping::ACCESSOR_RELATION_NAME,
            'writable' => false,
        ];

        return [
            $field('product', 'barcode', false),
            $field('productName', 'fullName'),
            $field('shortProductName', 'name'),
            $field('quantum', 'quantum'),
            $relationName('brand', 'legacyProductBrand'),
            $field('bestBeforDate', 'expirationDate'),
            $field('length', 'width'),
            $field('height', 'height'),
            $field('depth', 'depth'),
            $field('weight', 'weightGross'),
            $field('weightNetto', 'weightNet'),
            $field('boxQuantity', 'itemsPerBox'),
            $field('boxLength', 'boxWidth'),
            $field('boxHeight', 'boxHeight'),
            $field('boxDepth', 'boxDepth'),
            $field('boxWeight', 'boxWeight'),
            $field('boxWeightNetto', 'packagingWeightInBox'),
            $field('ispackage', 'isPackagingOptional'),
            $field('quantityOfCuintu', 'itemsPerPackage'),
            $field('packageLength', 'packageWidth'),
            $field('packageHeight', 'packageHeight'),
            $field('packageDepth', 'packageDepth'),
            $field('packageWeight', 'packageWeightGross'),
            $field('packageWeightNetto', 'packagingWeightInPackage'),
            $field('ingredients', 'notes'),
            $field('tnvedCode', 'tnvedCode'),
            $relationName('producerCountry', 'manufacturerCountry'),
            $relationName('producer', 'supplier'),
        ];
    }
}
