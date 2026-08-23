<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Enum;

/**
 * `filter` query parameter of the positions listing.
 *
 * `processed` / `confirmation` are booleanSelect fields: "yes" is stored as 1, "no" as -1, empty as NULL —
 * hence the `<> 1` / `IS NULL` style conditions instead of comparing with 0.
 */
enum PositionFilter: string
{
    case All = 'all';
    case Pending = 'pending';
    case Processed = 'processed';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public static function fromRequest(?string $raw): self
    {
        return $raw === null || $raw === '' ? self::All : (self::tryFrom($raw) ?? self::All);
    }

    /**
     * SQL condition on the position query table (no parameters needed), null = no restriction.
     */
    public function condition(): ?string
    {
        return match ($this) {
            self::All => null,
            self::Pending => '(processed IS NULL OR processed <> 1)',
            self::Processed => 'processed = 1',
            self::Accepted => 'processed = 1 AND confirmation = 1',
            self::Declined => 'processed = 1 AND (confirmation IS NULL OR confirmation <> 1)',
        };
    }
}
