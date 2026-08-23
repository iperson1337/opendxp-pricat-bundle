<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Compare;

/**
 * Canonical string form of a field value so that `6`, `6.0`, `"6,00"` and `" 6 "` compare equal.
 */
final class ValueNormalizer
{
    public function normalize(int|float|string|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        $string = trim((string) $value);
        $numeric = str_replace([' ', ','], ['', '.'], $string);
        if ($numeric !== '' && is_numeric($numeric)) {
            $float = (float) $numeric;

            return $float === floor($float) && abs($float) < PHP_INT_MAX
                ? (string) (int) $float
                : rtrim(rtrim(number_format($float, 6, '.', ''), '0'), '.');
        }

        return preg_replace('/\s+/u', ' ', $string) ?? $string;
    }

    public function isEmpty(int|float|string|bool|null $value): bool
    {
        return $value === null || ($value !== false && trim((string) $value) === '');
    }

    public function equals(int|float|string|bool|null $a, int|float|string|bool|null $b): bool
    {
        return mb_strtolower($this->normalize($a)) === mb_strtolower($this->normalize($b));
    }
}
