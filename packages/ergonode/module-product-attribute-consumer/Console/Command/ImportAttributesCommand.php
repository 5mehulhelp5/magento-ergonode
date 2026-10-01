<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ergonode\Core\Console\Report\ChangeReportConsoleWriter;
use Ergonode\ProductAttributeConsumer\Model\Import\AttributeImportProcess;
use Ergonode\Core\Model\Report\ChangeReport;

class ImportAttributesCommand extends Command
{
    private const string OPTION_BATCH_ONLY = 'batch';
    private const string OPTION_PAGE_SIZE = 'page-size';
    private const string OPTION_RESET = 'reset';
    private const string OPTION_MAX_BATCHES = 'max-batches';
    private const string OPTION_REPORT = 'report';
    private const string OPTION_REPORT_UNCHANGED = 'report-unchanged';

    public function __construct(
        private readonly AttributeImportProcess $attributeImportProcess,
        private readonly ChangeReport $changeReport,
        private readonly ChangeReportConsoleWriter $reportWriter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('ergonode:attributes:import');
        $this->setDescription('Import Ergonode attributes and synchronize their mapped options.');
        $this->addOption(
            self::OPTION_BATCH_ONLY,
            null,
            InputOption::VALUE_NONE,
            'Import only one batch and persist cursor for next run.'
        );
        $this->addOption(
            self::OPTION_PAGE_SIZE,
            null,
            InputOption::VALUE_OPTIONAL,
            'Requested GraphQL page size.',
            200
        );
        $this->addOption(
            self::OPTION_RESET,
            null,
            InputOption::VALUE_NONE,
            'Reset persisted cursor before importing.'
        );
        $this->addOption(
            self::OPTION_MAX_BATCHES,
            null,
            InputOption::VALUE_OPTIONAL,
            'Maximum number of batches for full import.',
            100
        );
        $this->addOption(
            self::OPTION_REPORT,
            null,
            InputOption::VALUE_NONE,
            'Print detailed per-attribute change report.'
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
            $this->changeReport->reset();

            if ((bool)$input->getOption(self::OPTION_RESET)) {
                $this->attributeImportProcess->reset();
            }

            $pageSize = max(1, (int)$input->getOption(self::OPTION_PAGE_SIZE));

            if ((bool)$input->getOption(self::OPTION_BATCH_ONLY)) {
                $result = $this->attributeImportProcess->executeBatch($pageSize);
                $output->writeln(sprintf(
                    'Batch imported=%d changed=%d unchanged=%d '
                    . 'created_attributes=%d auto_mapped=%d mapping_conflicts=%d '
                    . 'option_mappings=%d options_created=%d options_linked=%d review_required=%d '
                    . 'has_more=%s cursor=%s',
                    $result['imported'],
                    $result['changed'],
                    $result['unchanged'],
                    $result['created_attributes'],
                    $result['auto_mapped'],
                    $result['mapping_conflicts'],
                    $result['option_mappings'],
                    $result['options']['created'],
                    $result['options']['linked'],
                    $result['review_required'],
                    $result['has_more'] ? 'yes' : 'no',
                    $result['cursor'] ?: '-'
                ));
                $this->writeReportIfRequested($input, $output);

                return Command::SUCCESS;
            }

            $summary = $this->attributeImportProcess->executeUntilComplete(
                $pageSize,
                max(1, (int)$input->getOption(self::OPTION_MAX_BATCHES))
            );
            $output->writeln(sprintf(
                'Imported batches=%d imported=%d changed=%d unchanged=%d '
                . 'created_attributes=%d auto_mapped=%d mapping_conflicts=%d '
                . 'option_mappings=%d options_created=%d options_linked=%d review_required=%d has_more=%s',
                $summary['batches'],
                $summary['imported'],
                $summary['changed'],
                $summary['unchanged'],
                $summary['created_attributes'],
                $summary['auto_mapped'],
                $summary['mapping_conflicts'],
                $summary['option_mappings'],
                $summary['options']['created'],
                $summary['options']['linked'],
                $summary['review_required'],
                $summary['has_more'] ? 'yes' : 'no'
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
