<?php

declare(strict_types=1);

namespace Ergonode\Media\Console\Command;

use Ergonode\Media\Model\Index\LocalFileScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScanFilesCommand extends Command
{
    public function __construct(private readonly LocalFileScanner $scanner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ergonode:media:scan')->setDescription('Index existing local product media for reuse.')
            ->addOption('verify-content', null, InputOption::VALUE_NONE, 'Verify all file contents and report mapping mismatches without repairing files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $verifyContent = (bool)$input->getOption('verify-content');
        $result = $this->scanner->scan(false, $verifyContent);
        $output->writeln(sprintf(
            'Indexed: %d; reused: %d; missing entries removed: %d',
            $result['indexed'],
            $result['reused'],
            $result['removed']
        ));
        if ($verifyContent) {
            $output->writeln(sprintf('Content mismatches: %d; missing files: %d; unreadable files: %d. No files were repaired.',
                $result['mismatched'], $result['missing'], $result['unreadable']));
            if ($result['mismatched'] + $result['missing'] + $result['unreadable'] > 0) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
