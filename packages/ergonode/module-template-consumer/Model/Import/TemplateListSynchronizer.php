<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Api\TemplateSnapshotRefresherInterface;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class TemplateListSynchronizer implements TemplateSynchronizerInterface, TemplateSnapshotRefresherInterface
{
    public const string PROCESS_CODE = 'template_stream';
    private const int LEASE_SECONDS = 900;

    public function __construct(
        private readonly TemplateListImporter $listImporter,
        private readonly CursorStorage $cursorStorage,
        private readonly ChangeReport $changeReport
    ) {
    }

    public function execute(bool $resetCursor = false): array
    {
        $this->changeReport->reset();

        return $this->run($resetCursor, true);
    }

    public function refresh(): array
    {
        $this->changeReport->reset();

        return $this->run(false, false);
    }

    public function resetCursor(): void
    {
        $lockToken = $this->acquireLease();
        try {
            $this->cursorStorage->clearCursor(self::PROCESS_CODE, $lockToken);
        } finally {
            $this->cursorStorage->releaseLease(self::PROCESS_CODE, $lockToken);
        }
    }

    /**
     * @return array{
     *     events: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     cursor: string|null
     * }
     */
    private function run(bool $resetCursor, bool $synchronize): array
    {
        $lockToken = $this->acquireLease();

        try {
            if ($resetCursor) {
                $this->cursorStorage->clearCursor(self::PROCESS_CODE, $lockToken);
            }
            $result = $this->listImporter->execute($synchronize);
            if (!$this->cursorStorage->completeLease(self::PROCESS_CODE, $lockToken, null)) {
                throw new LocalizedException(
                    __('Ergonode template synchronization exceeded its 15-minute lock and was not checkpointed.')
                );
            }

            return $result;
        } catch (Throwable $exception) {
            $this->cursorStorage->releaseLease(self::PROCESS_CODE, $lockToken);
            throw $exception;
        }
    }

    private function acquireLease(): string
    {
        $lockToken = bin2hex(random_bytes(16));
        if (!$this->cursorStorage->acquireLease(
            self::PROCESS_CODE,
            $lockToken,
            self::LEASE_SECONDS
        )) {
            throw new LocalizedException(
                __('Ergonode template synchronization is already running. Try again shortly.')
            );
        }

        return $lockToken;
    }
}
