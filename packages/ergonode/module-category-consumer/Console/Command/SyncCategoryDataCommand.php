<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Console\Command;

use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

class SyncCategoryDataCommand extends Command
{
    public function __construct(
        private readonly CategoryDataSynchronizationProcessInterface $synchronizationProcess,
        private readonly CategoryReconciliationErrorFormatter $errorFormatter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('ergonode:categories:sync');
        $this->setDescription('Synchronize category names and optional attributes.');
        $this->addOption('reset-cursor', null, InputOption::VALUE_NONE, 'Read all categories again.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $stats = $this->synchronizationProcess->execute((bool)$input->getOption('reset-cursor'));
            $output->writeln(sprintf(
                'events=%d fetched=%d snapshots=%d attributes=%d cursor=%s',
                $stats['events'],
                $stats['fetched'],
                $stats['snapshots'],
                $stats['attributes'],
                $stats['cursor'] ?? 'null'
            ));

            return Command::SUCCESS;
        } catch (GraphQlRequestException $exception) {
            $output->writeln('<error>' . $this->errorFormatter->format($exception)['message'] . '</error>');
        } catch (Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
        }

        return Command::FAILURE;
    }
}
