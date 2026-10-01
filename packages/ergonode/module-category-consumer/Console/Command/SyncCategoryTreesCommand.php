<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Console\Command;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

class SyncCategoryTreesCommand extends Command
{
    private const string OPTION_RESET_CURSOR = 'reset-cursor';

    public function __construct(
        private readonly CategoryStructureSynchronizationProcessInterface $synchronizationProcess,
        private readonly CategoryReconciliationErrorFormatter $errorFormatter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('ergonode:category-trees:sync');
        $this->setDescription('Synchronize active configured Category Trees from categoryTreeStream.');
        $this->addOption(
            self::OPTION_RESET_CURSOR,
            null,
            InputOption::VALUE_NONE,
            'Reset the global stream cursor and fully reconcile every active configured Category Tree.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $stats = $this->synchronizationProcess->execute((bool)$input->getOption(self::OPTION_RESET_CURSOR));
            $output->writeln(sprintf(
                'events=%d trees=%d conflicts=%d cursor=%s',
                $stats['events'],
                $stats['trees'],
                $stats['conflicts'],
                $stats['cursor'] ?? 'null'
            ));
            if ($stats['conflicts'] > 0) {
                $output->writeln('<error>Category structure synchronization completed with conflicts.</error>');

                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (GraphQlRequestException $exception) {
            $output->writeln('<error>' . $this->errorFormatter->format($exception)['message'] . '</error>');
        } catch (Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
        }

        return Command::FAILURE;
    }
}
