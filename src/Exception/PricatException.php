<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Exception;

/**
 * Base class of every user-facing failure. Carries a translation key (domain `admin`) so controllers
 * can answer with a localized message while the raw message stays in the logs.
 */
class PricatException extends \RuntimeException
{
    /**
     * @param array<string, scalar> $translationParameters
     */
    public function __construct(
        string $message,
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @return array<string, scalar>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }
}
