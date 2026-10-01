<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Model\Import\TemplateListImporter;
use Ergonode\TemplateConsumer\Model\Import\TemplateListSynchronizer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TemplateListSynchronizerTest extends TestCase
{
    public function testRunsFullListAndCompletesLeaseWithoutCursor(): void
    {
        $lockToken = null;
        $cursorStorage = $this->createMock(CursorStorage::class);
        $cursorStorage->expects(self::once())
            ->method('acquireLease')
            ->willReturnCallback(static function (
                string $processCode,
                string $token,
                int $leaseSeconds
            ) use (&$lockToken): bool {
                self::assertSame(TemplateListSynchronizer::PROCESS_CODE, $processCode);
                self::assertSame(900, $leaseSeconds);
                $lockToken = $token;

                return true;
            });
        $cursorStorage->expects(self::never())->method('get');
        $cursorStorage->expects(self::once())
            ->method('completeLease')
            ->willReturnCallback(static function (
                string $processCode,
                string $token,
                ?string $cursor
            ) use (&$lockToken): bool {
                self::assertSame(TemplateListSynchronizer::PROCESS_CODE, $processCode);
                self::assertSame($lockToken, $token);
                self::assertNull($cursor);

                return true;
            });
        $cursorStorage->expects(self::never())->method('releaseLease');
        $importer = $this->createMock(TemplateListImporter::class);
        $result = [
            'events' => 1,
            'imported' => 1,
            'changed' => 1,
            'unchanged' => 0,
            'cursor' => null,
        ];
        $importer->expects(self::once())->method('execute')->with(true)->willReturn($result);

        self::assertSame($result, $this->synchronizer($importer, $cursorStorage)->execute());
    }

    public function testRefreshRunsSnapshotOnlyInsideTheSameLease(): void
    {
        $cursorStorage = $this->createMock(CursorStorage::class);
        $cursorStorage->method('acquireLease')->willReturn(true);
        $cursorStorage->expects(self::never())->method('clearCursor');
        $cursorStorage->expects(self::once())->method('completeLease')->willReturn(true);
        $importer = $this->createMock(TemplateListImporter::class);
        $importer->expects(self::once())->method('execute')->with(false)->willReturn([
            'events' => 0,
            'imported' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'cursor' => null,
        ]);

        $this->synchronizer($importer, $cursorStorage)->refresh();
    }

    public function testResetClearsLegacyCursorInsideAcquiredLease(): void
    {
        $cursorStorage = $this->createMock(CursorStorage::class);
        $cursorStorage->method('acquireLease')->willReturn(true);
        $cursorStorage->expects(self::once())->method('clearCursor');
        $cursorStorage->method('completeLease')->willReturn(true);
        $importer = $this->createStub(TemplateListImporter::class);
        $importer->method('execute')->willReturn([
            'events' => 0,
            'imported' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'cursor' => null,
        ]);

        $this->synchronizer($importer, $cursorStorage)->execute(true);
    }

    public function testCursorCanBeResetWithoutImportingTemplates(): void
    {
        $lockToken = null;
        $cursorStorage = $this->createMock(CursorStorage::class);
        $cursorStorage->expects(self::once())
            ->method('acquireLease')
            ->willReturnCallback(static function (
                string $processCode,
                string $token,
                int $leaseSeconds
            ) use (&$lockToken): bool {
                self::assertSame(TemplateListSynchronizer::PROCESS_CODE, $processCode);
                self::assertSame(900, $leaseSeconds);
                $lockToken = $token;

                return true;
            });
        $cursorStorage->expects(self::once())
            ->method('clearCursor')
            ->with(
                TemplateListSynchronizer::PROCESS_CODE,
                self::callback(static function (string $token) use (&$lockToken): bool {
                    return $token === $lockToken;
                })
            );
        $cursorStorage->expects(self::once())
            ->method('releaseLease')
            ->with(
                TemplateListSynchronizer::PROCESS_CODE,
                self::callback(static function (string $token) use (&$lockToken): bool {
                    return $token === $lockToken;
                })
            );
        $cursorStorage->expects(self::never())->method('completeLease');
        $importer = $this->createMock(TemplateListImporter::class);
        $importer->expects(self::never())->method('execute');

        $this->synchronizer($importer, $cursorStorage)->resetCursor();
    }

    public function testRejectsConcurrentSynchronization(): void
    {
        $cursorStorage = $this->createStub(CursorStorage::class);
        $cursorStorage->method('acquireLease')->willReturn(false);
        $importer = $this->createMock(TemplateListImporter::class);
        $importer->expects(self::never())->method('execute');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already running');

        $this->synchronizer($importer, $cursorStorage)->execute();
    }

    public function testFailureReleasesLeaseWithoutCompletingSnapshot(): void
    {
        $cursorStorage = $this->createMock(CursorStorage::class);
        $cursorStorage->method('acquireLease')->willReturn(true);
        $cursorStorage->expects(self::never())->method('completeLease');
        $cursorStorage->expects(self::once())->method('releaseLease');
        $importer = $this->createStub(TemplateListImporter::class);
        $importer->method('execute')->willThrowException(new RuntimeException('GraphQL failed.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GraphQL failed.');

        $this->synchronizer($importer, $cursorStorage)->execute();
    }

    private function synchronizer(
        TemplateListImporter $importer,
        CursorStorage $cursorStorage
    ): TemplateListSynchronizer {

        return new TemplateListSynchronizer($importer, $cursorStorage, new ChangeReport(new Json()));
    }
}
