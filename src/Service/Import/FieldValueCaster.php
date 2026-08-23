<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Import;

use Carbon\Carbon;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use OpenDxp\Model\DataObject\ClassDefinition\Data;

/**
 * Converts a raw XML text value into the PHP value the generated setter of a DataObject field expects.
 * The target type is derived from the field definition, so neither the XML tags nor the class fields
 * have to be listed in code: numeric → int|float|string (decimal), date → Carbon, boolean → bool, …
 */
final class FieldValueCaster
{
    private const array TRUE_TOKENS = ['1', 'true', 'yes', 'y', 'да'];
    private const array FALSE_TOKENS = ['0', 'false', 'no', 'n', 'нет'];

    private readonly string $dateFormat;

    public function __construct(PricatConfig $config)
    {
        $this->dateFormat = $config->dateFormat;
    }

    /**
     * @return int|float|string|bool|Carbon|null null when the value is empty or cannot be converted
     */
    public function cast(Data $definition, ?string $raw): int|float|string|bool|Carbon|null
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        return match (true) {
            $definition instanceof Data\Numeric => $this->castNumeric($definition, $raw),
            $definition instanceof Data\Date, $definition instanceof Data\Datetime => $this->castDate($raw),
            $definition instanceof Data\BooleanSelect, $definition instanceof Data\Checkbox => $this->castBoolean($raw),
            default => $raw,
        };
    }

    public function castNumeric(Data\Numeric $definition, string $raw): int|float|string|null
    {
        $normalized = str_replace([' ', ','], ['', '.'], $raw);
        if (!is_numeric($normalized)) {
            return null;
        }

        return match ($definition->getParameterTypeDeclaration()) {
            '?int' => (int) round((float) $normalized),
            '?string' => $this->decimalString($normalized, $definition->getDecimalPrecision()),
            default => (float) $normalized,
        };
    }

    public function castDate(string $raw): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat($this->dateFormat, $raw);
            if ($date instanceof Carbon && $date->format($this->dateFormat) === $raw) {
                return $date->startOfDay();
            }
        } catch (\Throwable) {
            // fall through to the lenient parser
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function castBoolean(string $raw): ?bool
    {
        $token = mb_strtolower($raw);

        return match (true) {
            \in_array($token, self::TRUE_TOKENS, true) => true,
            \in_array($token, self::FALSE_TOKENS, true) => false,
            default => null,
        };
    }

    /**
     * Decimal numeric fields are typed `string` by the class generator; keep the scale of the field.
     */
    private function decimalString(string $normalized, ?int $precision): string
    {
        if ($precision === null) {
            return (string) (float) $normalized;
        }

        return number_format((float) $normalized, $precision, '.', '');
    }
}
