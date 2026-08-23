<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Controller;

use Iperson1337\OpenDxpPricatBundle\Enum\PositionAction;
use Iperson1337\OpenDxpPricatBundle\Enum\PositionFilter;
use Iperson1337\OpenDxpPricatBundle\Enum\PricatAction;
use Iperson1337\OpenDxpPricatBundle\Exception\PricatException;
use Iperson1337\OpenDxpPricatBundle\Http\ApiResponseTrait;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use Iperson1337\OpenDxpPricatBundle\Service\Import\PricatImporter;
use Iperson1337\OpenDxpPricatBundle\Service\Position\PositionStatsRepository;
use OpenDxp\Bundle\AdminBundle\Controller\AdminAbstractController;
use OpenDxp\Model\DataObject\Pricat;
use OpenDxp\Model\DataObject\PricatPosition;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/pricat', name: 'iperson1337_opendxp_pricat_')]
final class PricatController extends AdminAbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PricatConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly PositionStatsRepository $stats,
    ) {
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->checkPermission($this->config->permission);
        [$offset, $limit] = $this->paging($request);

        $listing = new Pricat\Listing();
        $listing->setUnpublished(true);
        $listing->setOrderKey('id');
        $listing->setOrder('desc');
        $listing->setOffset($offset);
        $listing->setLimit($limit);

        $pricats = $listing->load();
        $stats = $this->stats->forPricats(array_map(static fn (Pricat $p): int => (int) $p->getId(), $pricats));

        $rows = [];
        foreach ($pricats as $pricat) {
            $rows[] = $this->pricatRow($pricat) + $stats[(int) $pricat->getId()]->toArray();
        }

        return $this->ok($rows, total: $listing->getTotalCount());
    }

    /**
     * Header of one pricat (for the positions tab) with its current position counters.
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $this->checkPermission($this->config->permission);
        $pricat = $this->loadPricat($id);
        $stats = $this->stats->forPricats([(int) $pricat->getId()]);

        return $this->ok($this->pricatRow($pricat) + $stats[(int) $pricat->getId()]->toArray());
    }

    /**
     * Positions of a pricat; `filter` = all (default) | pending | processed | accepted | declined.
     */
    #[Route('/{id}/positions', name: 'positions', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function positions(int $id, Request $request): JsonResponse
    {
        $this->checkPermission($this->config->permission);
        $pricat = $this->loadPricat($id);
        [$offset, $limit] = $this->paging($request);
        $filter = PositionFilter::fromRequest($request->query->get('filter'));

        $listing = new PricatPosition\Listing();
        $listing->setUnpublished(true);
        $condition = 'parentId = ?';
        if ($filter->condition() !== null) {
            $condition .= ' AND ' . $filter->condition();
        }
        $listing->setCondition($condition, [$pricat->getId()]);
        $listing->setOrderKey(['positonNumber', 'id']);
        $listing->setOrder(['asc', 'asc']);
        $listing->setOffset($offset);
        $listing->setLimit($limit);

        $rows = [];
        foreach ($listing->load() as $position) {
            $rows[] = $this->positionRow($position);
        }

        return $this->ok($rows, total: $listing->getTotalCount());
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request, PricatImporter $importer): JsonResponse
    {
        $this->checkPermission($this->config->permission);

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->fail('plugin_pimcore_pricat_error_no_file');
        }

        $xml = file_get_contents($file->getPathname());
        if ($xml === false || trim($xml) === '') {
            return $this->fail('plugin_pimcore_pricat_error_no_file');
        }

        try {
            $result = $importer->importXml($xml);
        } catch (PricatException $e) {
            $this->logger->warning('PRICAT import rejected', ['file' => $file->getClientOriginalName(), 'reason' => $e->getMessage()]);

            return $this->failWith($e);
        } catch (\Throwable $e) {
            $this->logger->error('PRICAT import failed', ['file' => $file->getClientOriginalName(), 'exception' => $e]);

            return $this->fail('plugin_pimcore_pricat_error_import_failed', ['%error%' => $e->getMessage()]);
        }

        return $this->ok(
            [
                'id' => $result->pricat->getId(),
                'positions' => $result->positionCount,
                'unmappedHeaderTags' => $result->unmappedHeaderTags,
                'unmappedPositionTags' => $result->unmappedPositionTags,
            ],
            'plugin_pimcore_pricat_import_success',
        );
    }

    protected function translator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * @return array{0: int, 1: int} offset, limit
     */
    private function paging(Request $request): array
    {
        $offset = max(0, $request->query->getInt('start', 0));
        $limit = $request->query->getInt('limit', $this->config->pageSize);
        $limit = $limit < 1 ? $this->config->pageSize : min($limit, 500);

        return [$offset, $limit];
    }

    private function loadPricat(int $id): Pricat
    {
        $pricat = Pricat::getById($id);
        if ($pricat === null) {
            throw new NotFoundHttpException(sprintf('Pricat #%d not found', $id));
        }

        return $pricat;
    }

    /**
     * @return array<string, mixed>
     */
    private function pricatRow(Pricat $pricat): array
    {
        return [
            'id' => $pricat->getId(),
            'number' => $pricat->getNumber(),
            'docFlowId' => $pricat->getDocFlowId(),
            'date' => $pricat->getDate()?->format('Y-m-d'),
            'dateFrom' => $pricat->getDateFrom()?->format('Y-m-d'),
            'dateTo' => $pricat->getDateTo()?->format('Y-m-d'),
            'timeFrom' => $pricat->getTimeFrom(),
            'supplier' => $pricat->getSupplier(),
            'buyer' => $pricat->getBuyer(),
            'vat' => $pricat->getVat(),
            'actionCode' => $pricat->getAction() === null ? null : (int) $pricat->getAction(),
            'action' => $this->translator->trans(PricatAction::labelKeyFor($pricat->getAction()), [], 'admin'),
            'sender' => $pricat->getSender(),
            'recipient' => $pricat->getRecipient(),
            'totalRejectionComment' => $pricat->getTotalrejectioncomment(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function positionRow(PricatPosition $position): array
    {
        $confirmation = $position->getConfirmation();

        return [
            'id' => $position->getId(),
            'positionNumber' => $position->getPositonNumber() === null ? null : (int) $position->getPositonNumber(),
            'actionCode' => $position->getAction(),
            'action' => $this->translator->trans(PositionAction::labelKeyFor($position->getAction()), [], 'admin'),
            'product' => $position->getProduct(),
            'productName' => $position->getProductName(),
            'region' => $position->getRegion(),
            'processed' => $position->getProcessed() === true,
            'confirmation' => $confirmation,
            'confirmationLabel' => $this->translator->trans(match ($confirmation) {
                true => 'plugin_pimcore_pricat_confirmation_accepted',
                false => 'plugin_pimcore_pricat_confirmation_declined',
                null => 'plugin_pimcore_pricat_confirmation_pending',
            }, [], 'admin'),
            'rejectionComment' => $position->getRejectionComment(),
        ];
    }
}
