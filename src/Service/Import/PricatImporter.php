<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Service\Import;

use Iperson1337\OpenDxpPricatBundle\Exception\DuplicatePricatException;
use Iperson1337\OpenDxpPricatBundle\Exception\PricatException;
use Iperson1337\OpenDxpPricatBundle\Model\ImportResult;
use Iperson1337\OpenDxpPricatBundle\Model\ParsedPricat;
use Iperson1337\OpenDxpPricatBundle\Model\PricatConfig;
use Iperson1337\OpenDxpPricatBundle\Service\Xml\PricatXmlParser;
use OpenDxp\Db;
use OpenDxp\Model\DataObject;
use OpenDxp\Model\DataObject\Pricat;
use OpenDxp\Model\DataObject\PricatPosition;
use OpenDxp\Model\Element\Service as ElementService;
use Psr\Log\LoggerInterface;

/**
 * PRICAT XML → one published `Pricat` object in the configured folder + one `PricatPosition`
 * child per <POSITION>, all inside a single DB transaction.
 */
final class PricatImporter
{
    public function __construct(
        private readonly PricatXmlParser $parser,
        private readonly DataObjectHydrator $hydrator,
        private readonly PricatDuplicateFinder $duplicateFinder,
        private readonly PricatConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws PricatException on invalid XML, empty document or duplicate import
     */
    public function importXml(string $xml): ImportResult
    {
        return $this->import($this->parser->parse($xml));
    }

    /**
     * @throws PricatException
     */
    public function import(ParsedPricat $parsed): ImportResult
    {
        $folder = $this->folder();

        $pricat = new Pricat();
        $pricat->setParent($folder);
        $pricat->setPublished(true);
        $unmappedHeader = $this->hydrator->hydrate(
            $pricat,
            $parsed->header,
            $this->config->pricatFieldAliases,
            $this->config->ignoredTags,
        );
        $pricat->setKey($this->pricatKey($pricat));

        $identity = $this->duplicateFinder->identityOf($pricat);
        $existingId = $this->duplicateFinder->findExisting($identity);
        if ($existingId !== null) {
            throw new DuplicatePricatException($identity, $existingId);
        }

        $unmappedPositions = [];
        $db = Db::get();
        $db->beginTransaction();
        try {
            $pricat->save(['versionNote' => 'PRICAT import']);

            foreach ($parsed->positions as $index => $row) {
                $position = new PricatPosition();
                $position->setParent($pricat);
                $position->setPricat($pricat);
                $position->setPublished(true);

                $unmapped = $this->hydrator->hydrate(
                    $position,
                    $row,
                    $this->config->positionFieldAliases,
                    $this->config->ignoredTags,
                );
                $unmappedPositions = array_values(array_unique([...$unmappedPositions, ...$unmapped]));

                // A position that already carries a supplier answer (<CONFIRMATION>) needs no manual processing.
                $position->setProcessed($position->getConfirmation() === null ? null : true);
                $position->setKey($this->positionKey($position, $index));
                $position->save(['versionNote' => 'PRICAT import']);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            throw $e;
        }

        if ($unmappedHeader !== [] || $unmappedPositions !== []) {
            $this->logger->info('PRICAT import: XML tags without a matching DataObject field were skipped', [
                'pricat_id' => $pricat->getId(),
                'header_tags' => $unmappedHeader,
                'position_tags' => $unmappedPositions,
            ]);
        }

        $this->logger->info('PRICAT imported', [
            'pricat_id' => $pricat->getId(),
            'number' => $pricat->getNumber(),
            'positions' => \count($parsed->positions),
        ]);

        return new ImportResult($pricat, \count($parsed->positions), $unmappedHeader, $unmappedPositions);
    }

    private function folder(): DataObject\Folder
    {
        $folder = DataObject\Service::createFolderByPath($this->config->folderPath);
        if (!$folder instanceof DataObject\Folder) {
            throw new \RuntimeException(sprintf('Could not create DataObject folder "%s"', $this->config->folderPath));
        }

        return $folder;
    }

    private function pricatKey(Pricat $pricat): string
    {
        $key = implode('-', array_filter([
            'pricat',
            $pricat->getNumber(),
            $pricat->getDate()?->format('Y-m-d'),
            uniqid('', true),
        ], static fn (?string $part): bool => $part !== null && $part !== ''));

        return ElementService::getValidKey($key, DataObject::OBJECT_TYPE_OBJECT);
    }

    private function positionKey(PricatPosition $position, int $index): string
    {
        $number = $position->getPositonNumber();
        $key = sprintf('position-%s', $number === null ? (string) ($index + 1) : (string) (int) $number);
        $position->setKey(ElementService::getValidKey($key, DataObject::OBJECT_TYPE_OBJECT));

        // positions of one PRICAT may repeat a number; getUniqueKey appends a numeric suffix on collision
        return DataObject\Service::getUniqueKey($position);
    }
}
