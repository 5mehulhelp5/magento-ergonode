<?php

declare(strict_types=1);

namespace Ergonode\Core\Console\Report;

use Ergonode\Core\Model\Report\ChangeReport;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class ChangeReportConsoleWriter
{
    public function __construct(
        private readonly ChangeReport $changeReport
    ) {
    }

    public function write(OutputInterface $output, bool $includeUnchanged = false): void
    {
        $rows = [];
        $rendered = false;
        foreach ($this->changeReport->iterateEntries($includeUnchanged) as $entry) {
            $rows[] = [
                $entry['entity'],
                $entry['identifier'],
                $entry['action'],
                $entry['message'],
                $this->changeReport->formatDetails($entry['details']),
            ];
            if (count($rows) === 200) {
                $this->renderRows($output, $rows);
                $rows = [];
                $rendered = true;
            }
        }
        if ($rows !== []) {
            $this->renderRows($output, $rows);
        } elseif (!$rendered) {
            $output->writeln('<info>No reportable changes.</info>');
        }
    }

    /** @param list<list<string>> $rows */
    private function renderRows(OutputInterface $output, array $rows): void
    {
        $table = new Table($output);
        $table->setHeaders(['Entity', 'Identifier', 'Action', 'Message', 'Details']);
        $table->setRows($rows);
        $table->render();
    }
}
