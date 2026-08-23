<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Import;

use OpenDxp\Model\DataObject\ClassDefinition;
use OpenDxp\Model\DataObject\ClassDefinition\Data;
use OpenDxp\Model\DataObject\Concrete;

/**
 * Writes a `lower-cased tag => raw text` row onto a DataObject.
 *
 * Resolution order for every tag: explicit alias → field whose name equals the tag case-insensitively
 * → unmapped (returned to the caller, never an error: suppliers add tags we do not store).
 */
final class DataObjectHydrator
{
    /** @var array<string, array<string, Data>> class name => lower-cased field name => definition */
    private array $fieldIndex = [];

    public function __construct(private readonly FieldValueCaster $caster)
    {
    }

    /**
     * @param array<string, string> $row          lower-cased tag => raw text
     * @param array<string, string> $aliases      lower-cased tag => field name
     * @param list<string>          $ignoredTags  lower-cased
     *
     * @return list<string> tags that matched no field (excluding $ignoredTags)
     */
    public function hydrate(Concrete $object, array $row, array $aliases = [], array $ignoredTags = []): array
    {
        $class = $object->getClass();
        $unmapped = [];

        foreach ($row as $tag => $raw) {
            if (\in_array($tag, $ignoredTags, true)) {
                continue;
            }

            $definition = $this->resolve($class, $tag, $aliases);
            if ($definition === null) {
                $unmapped[] = $tag;

                continue;
            }

            $object->set($definition->getName(), $this->caster->cast($definition, $raw));
        }

        return $unmapped;
    }

    public function resolve(ClassDefinition $class, string $tag, array $aliases = []): ?Data
    {
        $index = $this->index($class);
        $fieldName = $aliases[$tag] ?? $tag;

        return $index[mb_strtolower($fieldName)] ?? null;
    }

    /**
     * @return array<string, Data>
     */
    private function index(ClassDefinition $class): array
    {
        $className = (string) $class->getName();
        if (!isset($this->fieldIndex[$className])) {
            $index = [];
            foreach ($class->getFieldDefinitions() as $definition) {
                if ($definition instanceof Data\Localizedfields) {
                    foreach ($definition->getFieldDefinitions() as $localized) {
                        $index[mb_strtolower($localized->getName())] = $localized;
                    }

                    continue;
                }
                $index[mb_strtolower($definition->getName())] = $definition;
            }
            $this->fieldIndex[$className] = $index;
        }

        return $this->fieldIndex[$className];
    }
}
