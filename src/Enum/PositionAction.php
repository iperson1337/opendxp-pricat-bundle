<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Enum;

/**
 * <ACTION> of a PRICAT <POSITION> (line-level action request).
 */
enum PositionAction: int
{
    case Add = 2;
    case Delete = 3;
    case Update = 4;

    public const string UNKNOWN_LABEL_KEY = 'plugin_pimcore_pricat_position_action_unknown';

    public function labelKey(): string
    {
        return match ($this) {
            self::Add => 'plugin_pimcore_pricat_position_action_add',
            self::Delete => 'plugin_pimcore_pricat_position_action_delete',
            self::Update => 'plugin_pimcore_pricat_position_action_update',
        };
    }

    public static function labelKeyFor(mixed $raw): string
    {
        $code = is_numeric($raw) ? (int) $raw : null;

        return $code === null ? self::UNKNOWN_LABEL_KEY : (self::tryFrom($code)?->labelKey() ?? self::UNKNOWN_LABEL_KEY);
    }
}
