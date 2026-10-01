<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Console\Command;

use Ergonode\Core\Console\Report\ChangeReportConsoleWriter;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportTemplatesCommand extends Command
{
    private const string OPTION_RESET = 'reset';
    private const string OPTION_REPORT = 'report';
    private const string OPTION_REPORT_UNCHANGED = 'report-unchanged';

    public function __construct(
        private readonly TemplateSynchronizerInterface $templateSynchronizer,
        private readonly ChangeReport $changeReport,
        private readonly ChangeReportConsoleWriter $reportWriter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('ergonode:templates:import');
        $this->setDescription('Import Ergonode templates and update their Magento attribute-set mappings.');
        $this->addOption(
            self::OPTION_RESET,
            null,
            InputOption::VALUE_NONE,
            'Clear legacy cursor state before the full template list synchronization.'
        );
        $this->addOption(
            self::OPTION_REPORT,
            null,
            InputOption::VALUE_NONE,
            'Print detailed per-template change report.'
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

            $result = $this->templateSynchronizer->execute((bool)$input->getOption(self::OPTION_RESET));
            $output->writeln(sprintf(
                'Templates events=%d imported=%d changed=%d unchanged=%d cursor=%s',
                $result['events'],
                $result['imported'],
                $result['changed'],
                $result['unchanged'],
                $result['cursor'] ?: '-'
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
