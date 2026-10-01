<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ergonode\Core\Console\Report\ChangeReportConsoleWriter;
use Ergonode\ProductAttributeConsumer\Api\OptionSynchronizationProcessInterface;

class SyncOptionsCommand extends Command
{
    private const string OPTION_MAPPING_ID = 'mapping-id';
    private const string OPTION_REPORT = 'report';
    private const string OPTION_REPORT_UNCHANGED = 'report-unchanged';

    public function __construct(
        private readonly OptionSynchronizationProcessInterface $optionSynchronizationProcess,
        private readonly ChangeReportConsoleWriter $reportWriter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('ergonode:options:sync');
        $this->setDescription(
            'Repair or explicitly resynchronize Ergonode options outside the normal attribute import flow.'
        );
        $this->addOption(
            self::OPTION_MAPPING_ID,
            null,
            InputOption::VALUE_OPTIONAL,
            'Attribute mapping ID to synchronize. When omitted, all option-mappable mappings are synchronized.'
        );
        $this->addOption(
            self::OPTION_REPORT,
            null,
            InputOption::VALUE_NONE,
            'Print detailed per-option sync report.'
        );
        $this->addOption(
            self::OPTION_REPORT_UNCHANGED,
            null,
            InputOption::VALUE_NONE,
            'Include unchanged rows in the detailed report.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $mappingId = (int)($input->getOption(self::OPTION_MAPPING_ID) ?: 0);
            $result = $this->optionSynchronizationProcess->execute($mappingId > 0 ? $mappingId : null);

            foreach ($result['mappings'] as $mapping) {
                $stats = $mapping['stats'];
                $output->writeln(sprintf(
                    'Mapping #%d %s -> %s: created=%d linked=%d labels=%d ' .
                    'sort_order=%d unchanged=%d skipped=%d errors=%d',
                    (int)$mapping['mapping_id'],
                    (string)$mapping['ergonode_attribute_code'],
                    (string)$mapping['magento_attribute_code'],
                    $stats['created'],
                    $stats['linked'],
                    $stats['labels_updated'],
                    $stats['sort_order_updated'],
                    $stats['unchanged'],
                    $stats['skipped'],
                    $stats['errors']
                ));
            }

            $summary = $result['summary'];
            $output->writeln(sprintf(
                'Total mappings=%d created=%d linked=%d mappings_inserted=%d mappings_updated=%d ' .
                'labels=%d sort_order=%d unchanged=%d skipped=%d errors=%d',
                count($result['mappings']),
                $summary['created'],
                $summary['linked'],
                $summary['mappings_inserted'],
                $summary['mappings_updated'],
                $summary['labels_updated'],
                $summary['sort_order_updated'],
                $summary['unchanged'],
                $summary['skipped'],
                $summary['errors']
            ));
            $this->writeReportIfRequested($input, $output);

            return Command::SUCCESS;
        } catch (LocalizedException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    private function writeReportIfRequested(InputInterface $input, OutputInterface $output): void
    {
        if (!(bool)$input->getOption(self::OPTION_REPORT)) {
            return;
        }

        $this->reportWriter->write($output, (bool)$input->getOption(self::OPTION_REPORT_UNCHANGED));
    }
}
