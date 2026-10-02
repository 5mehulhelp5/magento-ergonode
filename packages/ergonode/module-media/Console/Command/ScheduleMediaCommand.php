<?php

declare(strict_types=1);

namespace Ergonode\Media\Console\Command;

use Ergonode\Media\Model\Import\StreamScheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduleMediaCommand extends Command
{
    public function __construct(private readonly StreamScheduler $scheduler)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->setName('ergonode:media:schedule')
            ->setDescription('Schedule Ergonode multimedia changes.')
            ->addOption('max-pages', null, InputOption::VALUE_REQUIRED, 'Maximum pages.', '100');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->scheduler->schedule(max(1, (int)$input->getOption('max-pages')));
        $output->writeln(sprintf(
            'Pages: %d; assets: %d; products: %d',
            $result['pages'],
            $result['assets'],
            $result['products']
        ));
        return self::SUCCESS;
    }
}
