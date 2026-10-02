<?php

declare(strict_types=1);

namespace Ergonode\Media\Console\Command;

use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListFilesCommand extends Command
{
    public function __construct(private readonly LocalFileIndexInterface $index)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ergonode:media:list')->setDescription('List the local product media index.')
            ->addOption('after', null, InputOption::VALUE_REQUIRED, 'Continue after this indexed path.', '')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows (1-1000).', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->index->page((string)$input->getOption('after'), (int)$input->getOption('limit'));
        $table = new Table($output);
        $table->setHeaders(['Path', 'SHA-256', 'Bytes']);
        foreach ($rows as $row) {
            $table->addRow([$row['path'], bin2hex($row['content_hash']), $row['size']]);
        }
        $table->render();

        return self::SUCCESS;
    }
}
