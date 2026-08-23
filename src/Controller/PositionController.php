<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Controller;

use Iperson1337\OpenDxpPricatBundle\Exception\PricatException;
use Iperson1337\OpenDxpPricatBundle\Http\ApiResponseTrait;
use Iperson1337\OpenDxpPricatBundle\Model\CompareRow;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use Iperson1337\OpenDxpPricatBundle\Service\Compare\PositionAcceptor;
use Iperson1337\OpenDxpPricatBundle\Service\Compare\PositionDecliner;
use Iperson1337\OpenDxpPricatBundle\Service\Compare\PositionProductComparator;
use Iperson1337\OpenDxpPricatBundle\Service\Product\ProductFinder;
use OpenDxp\Bundle\AdminBundle\Controller\AdminAbstractController;
use OpenDxp\Model\DataObject\PricatPosition;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/pricat/position', name: 'iperson1337_opendxp_pricat_position_')]
final class PositionController extends AdminAbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PricatConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/{id}/compare', name: 'compare', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function compare(int $id, ProductFinder $finder, PositionProductComparator $comparator): JsonResponse
    {
        $this->checkPermission($this->config->permission);
        $position = $this->loadPosition($id);

        if ($position->getProcessed() === true) {
            return $this->fail('plugin_pimcore_pricat_error_position_processed', ['%id%' => $id]);
        }

        try {
            $product = $finder->findForPosition($position);
            $rows = $comparator->compare($position, $product);
        } catch (PricatException $e) {
            return $this->failWith($e);
        }

        return $this->ok([
            'position' => ['id' => $position->getId(), 'product' => $position->getProduct()],
            'product' => ['id' => $product->getId(), 'path' => $product->getFullPath()],
            'rows' => array_map(static fn (CompareRow $row): array => $row->toArray(), $rows),
        ]);
    }

    /**
     * Body (JSON or form): optional `fields` — product fields to write. Absent = every writable
     * mapping; empty list = write nothing, only mark the position accepted. A field outside the
     * writable mapping is an error, never silently skipped.
     */
    #[Route('/{id}/accept', name: 'accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(int $id, Request $request, PositionAcceptor $acceptor): JsonResponse
    {
        $this->checkPermission($this->config->permission);
        $position = $this->loadPosition($id);

        $payload = $request->getPayload();
        $fields = null;
        if ($payload->has('fields')) {
            $fields = array_values(array_map('strval', $payload->all('fields')));
        }

        try {
            $written = $acceptor->accept($position, $fields);
        } catch (PricatException $e) {
            return $this->failWith($e);
        } catch (\InvalidArgumentException $e) {
            return $this->fail('plugin_pimcore_pricat_error_accept_fields', ['%error%' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('PRICAT position accept failed', ['position_id' => $id, 'exception' => $e]);

            return $this->fail('plugin_pimcore_pricat_error_accept_failed', ['%error%' => $e->getMessage()]);
        }

        return $this->ok(['written' => $written], 'plugin_pimcore_pricat_position_accepted');
    }

    #[Route('/{id}/decline', name: 'decline', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decline(int $id, Request $request, PositionDecliner $decliner): JsonResponse
    {
        $this->checkPermission($this->config->permission);
        $position = $this->loadPosition($id);

        $comment = trim((string) $request->request->get('comment', ''));
        if ($comment === '') {
            return $this->fail('plugin_pimcore_pricat_error_comment_required');
        }

        try {
            $decliner->decline($position, $comment);
        } catch (PricatException $e) {
            return $this->failWith($e);
        } catch (\Throwable $e) {
            $this->logger->error('PRICAT position decline failed', ['position_id' => $id, 'exception' => $e]);

            return $this->fail('plugin_pimcore_pricat_error_decline_failed', ['%error%' => $e->getMessage()]);
        }

        return $this->ok([], 'plugin_pimcore_pricat_position_declined');
    }

    protected function translator(): TranslatorInterface
    {
        return $this->translator;
    }

    private function loadPosition(int $id): PricatPosition
    {
        $position = PricatPosition::getById($id);
        if ($position === null) {
            throw new NotFoundHttpException(sprintf('PricatPosition #%d not found', $id));
        }

        return $position;
    }
}
