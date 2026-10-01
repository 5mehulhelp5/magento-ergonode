<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Console\Command;

use Ergonode\ProductConsumer\Model\Import\ProductStreamScheduler;
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;
use Ergonode\ProductConsumer\Model\Queue\ProductImportQueuePublisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportProductsCommand extends Command
{
    public function __construct(
        private readonly ProductStreamScheduler $scheduler,
        private readonly ProductImportWorkRepositoryInterface $workRepository,
        private readonly ProductImportQueuePublisher $queuePublisher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ergonode:products:import')
            ->setDescription('Schedule changed and deleted Ergonode products for durable Magento import.')
            ->addOption('max-pages', null, InputOption::VALUE_REQUIRED, 'Maximum pages per stream.', '100')
            ->addOption('retry-failed', null, InputOption::VALUE_NONE, 'Return failed items to the queue.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ((bool)$input->getOption('retry-failed')) {
            $retried = $this->workRepository->retryFailed();
            if ($retried > 0) {
                $this->queuePublisher->dispatch();
            }
            $output->writeln(sprintf('Retried failed products: %d', $retried));
        }
        $summary = $this->scheduler->schedule(max(1, (int)$input->getOption('max-pages')));
        $output->writeln(sprintf(
            'Pages: %d; changed: %d; deleted: %d; throttled: %s',
            $summary['pages'],
            $summary['changed'],
            $summary['deleted'],
            $summary['throttled'] ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }
}
