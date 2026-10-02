<?php

declare(strict_types=1);

namespace Ergonode\Media\Console\Command;

use Ergonode\Media\Model\Index\LocalFileScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScanFilesCommand extends Command
{
    public function __construct(private readonly LocalFileScanner $scanner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ergonode:media:scan')->setDescription('Index existing local product media for reuse.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->scanner->scan();
        $output->writeln(sprintf(
            'Indexed: %d; reused: %d; missing entries removed: %d',
            $result['indexed'],
            $result['reused'],
            $result['removed']
        ));

        return self::SUCCESS;
    }
}
