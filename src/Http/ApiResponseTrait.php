<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Http;

use Iperson1337\OpenDxpPricatBundle\Exception\PricatException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Uniform `{success, message, data, total?}` envelope the admin JS expects.
 * Errors are returned with HTTP 200 on purpose: ExtJS form submits treat non-2xx as a transport
 * failure and never read the message; `success: false` is the application-level signal.
 */
trait ApiResponseTrait
{
    abstract protected function translator(): TranslatorInterface;

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    protected function ok(array $data = [], ?string $messageKey = null, ?int $total = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $messageKey === null ? '' : $this->translator()->trans($messageKey, [], 'admin'),
            'data' => $data,
        ];
        if ($total !== null) {
            $payload['total'] = $total;
        }

        return new JsonResponse($payload);
    }

    /**
     * @param array<string, scalar> $parameters
     */
    protected function fail(string $messageKey, array $parameters = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->translator()->trans($messageKey, $parameters, 'admin'),
            'data' => [],
        ], $status);
    }

    protected function failWith(PricatException $exception): JsonResponse
    {
        return $this->fail($exception->getTranslationKey(), $exception->getTranslationParameters());
    }
}
