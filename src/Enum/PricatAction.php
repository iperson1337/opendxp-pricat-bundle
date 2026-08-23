<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Enum;

/**
 * <ACTION> of the PRICAT header (document-level message function).
 */
enum PricatAction: int
{
    case PriceList = 9;
    case AnswerAccepted = 51;
    case AnswerAcceptedPartially = 52;
    case AnswerDeclined = 99;

    public const string UNKNOWN_LABEL_KEY = 'plugin_pimcore_pricat_action_unknown';

    public function labelKey(): string
    {
        return match ($this) {
            self::PriceList => 'plugin_pimcore_pricat_action_price_list',
            self::AnswerAccepted => 'plugin_pimcore_pricat_action_answer_accepted',
            self::AnswerAcceptedPartially => 'plugin_pimcore_pricat_action_answer_accepted_partially',
            self::AnswerDeclined => 'plugin_pimcore_pricat_action_answer_declined',
        };
    }

    /**
     * Translation key for a raw stored value (float/string/null) — unknown codes get the fallback key.
     */
    public static function labelKeyFor(mixed $raw): string
    {
        $code = is_numeric($raw) ? (int) $raw : null;

        return $code === null ? self::UNKNOWN_LABEL_KEY : (self::tryFrom($code)?->labelKey() ?? self::UNKNOWN_LABEL_KEY);
    }
}
