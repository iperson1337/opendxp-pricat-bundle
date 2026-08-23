<?php

declare(strict_types=1);

namespace Iperson1337\OpenDxpPricatBundle\Command;

use Iperson1337\OpenDxpPricatBundle\Exception\PricatException;
use Iperson1337\OpenDxpPricatBundle\Service\Import\PricatImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI counterpart of the admin "Import" button: `bin/console pricat:import file1.xml [file2.xml ...]`.
 */
#[AsCommand(
    name: 'pricat:import',
    description: 'Import one or more PRICAT XML files into Pricat / PricatPosition objects',
)]
final class ImportPricatCommand extends Command
{
    public function __construct(private readonly PricatImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('files', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Path(s) to PRICAT XML files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failed = 0;

        /** @var list<string> $files */
        $files = $input->getArgument('files');
        foreach ($files as $file) {
            $xml = is_readable($file) ? file_get_contents($file) : false;
            if ($xml === false) {
                $io->error(sprintf('%s: cannot read file', $file));
                ++$failed;

                continue;
            }

            try {
                $result = $this->importer->importXml($xml);
            } catch (PricatException $e) {
                $io->warning(sprintf('%s: %s', $file, $e->getMessage()));
                ++$failed;

                continue;
            }

            $io->success(sprintf(
                '%s → Pricat #%d (%s), %d positions%s',
                $file,
                $result->pricat->getId(),
                $result->pricat->getFullPath(),
                $result->positionCount,
                $result->unmappedHeaderTags === [] && $result->unmappedPositionTags === []
                    ? ''
                    : sprintf('; unmapped tags: %s', implode(', ', [...$result->unmappedHeaderTags, ...$result->unmappedPositionTags])),
            ));
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
