<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\AttributeDefinitionLoader;
use Ergonode\AttributeConsumer\Model\Import\AttributeDefinitionSynchronization;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionSnapshotInterface;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionCheckRecorderInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class AttributeDefinitionSynchronizationTest extends TestCase
{
    public function testDeletionAloneReconcilesCompleteSnapshot(): void
    {
        $loader = $this->createMock(AttributeDefinitionLoader::class);
        $loader->expects(self::exactly(2))->method('changes')->willReturnOnConsecutiveCalls(
            ['cursor' => 'old-attribute', 'changed' => false],
            ['cursor' => 'new-deletion', 'changed' => true]
        );
        $loader->expects(self::once())->method('load')->willReturn([]);
        $snapshot = $this->createMock(AttributeDefinitionSnapshotInterface::class);
        $snapshot->method('getState')->willReturn($this->state());
        $snapshot->expects(self::once())->method('replace')->with([], [
            ...$this->state(), 'deleted' => 'new-deletion',
        ])->willReturn(['removed']);
        $this->service($loader, $snapshot, ['running', 'changes_detected', 'changed'])->synchronize();
    }

    public function testFailedFullDownloadPreservesSnapshotAndBothCursors(): void
    {
        $loader = $this->createMock(AttributeDefinitionLoader::class);
        $loader->method('changes')->willReturn(['cursor' => 'next', 'changed' => true]);
        $loader->expects(self::once())->method('load')->willThrowException(new RuntimeException('second page failed'));
        $snapshot = $this->createMock(AttributeDefinitionSnapshotInterface::class);
        $snapshot->expects(self::never())->method('replace');
        $snapshot->method('getState')->willReturn($this->state());
        $service = $this->service($loader, $snapshot, ['running', 'changes_detected', 'failed']);
        $this->expectExceptionMessage('second page failed');
        $service->synchronize();
    }

    public function testUnchangedStreamsDoNotDownloadOrRewriteSnapshot(): void
    {
        $loader = $this->createMock(AttributeDefinitionLoader::class);
        $loader->method('changes')->willReturn(['cursor' => null, 'changed' => false]);
        $loader->expects(self::never())->method('load');
        $snapshot = $this->createMock(AttributeDefinitionSnapshotInterface::class);
        $snapshot->method('getState')->willReturn($this->state());
        $snapshot->expects(self::never())->method('replace');
        $this->service($loader, $snapshot, ['running', 'no_changes'])->synchronize();
    }

    public function testInitialSnapshotIsNotReportedAsNewChange(): void
    {
        $loader = $this->createStub(AttributeDefinitionLoader::class);
        $loader->method('changes')->willReturn(['cursor' => 'initial', 'changed' => true]);
        $snapshot = $this->createStub(AttributeDefinitionSnapshotInterface::class);
        $this->service($loader, $snapshot, ['running', 'initialized'])->synchronize();
    }

    public function testForcedUnchangedRefreshKeepsNoChangesResult(): void
    {
        $loader = $this->createMock(AttributeDefinitionLoader::class);
        $loader->method('changes')->willReturn(['cursor' => null, 'changed' => false]);
        $loader->expects(self::once())->method('load')->willReturn([]);
        $snapshot = $this->createStub(AttributeDefinitionSnapshotInterface::class);
        $snapshot->method('getState')->willReturn($this->state());
        $this->service($loader, $snapshot, ['running', 'no_changes'])->synchronize(true);
    }

    public function testLockConflictDoesNotOverwriteOwnersObservation(): void
    {
        $loader = $this->createMock(AttributeDefinitionLoader::class);
        $loader->expects(self::never())->method('changes');
        $service = $this->service($loader, $this->createStub(AttributeDefinitionSnapshotInterface::class), [], false);
        $this->expectExceptionMessage('Attribute synchronization is already running.');
        $service->synchronize();
    }

    public function testNewLanguageRefreshesSnapshotEvenWhenStreamsAreUnchanged(): void
    {
        $loader = $this->createMock(AttributeDefinitionLoader::class);
        $loader->method('changes')->willReturn(['cursor' => null, 'changed' => false]);
        $loader->expects(self::once())->method('load')->willReturn([]);
        $snapshot = $this->createMock(AttributeDefinitionSnapshotInterface::class);
        $snapshot->method('getState')->willReturn($this->state());
        $snapshot->expects(self::once())->method('replace')->with([], self::callback(
            static fn (array $state): bool => $state['source'] === hash(
                'sha256',
                'test|https://example.test|read|0|["en_GB","pl_PL"]'
            )
        ))->willReturn([]);
        $this->service($loader, $snapshot, ['running', 'initialized'], true, ['pl_PL', 'en_GB'])->synchronize();
    }

    public function testDuplicateLanguageMappingDoesNotRefreshSnapshot(): void
    {
        $loader = $this->createMock(AttributeDefinitionLoader::class);
        $loader->method('changes')->willReturn(['cursor' => null, 'changed' => false]);
        $loader->expects(self::never())->method('load');
        $snapshot = $this->createStub(AttributeDefinitionSnapshotInterface::class);
        $snapshot->method('getState')->willReturn($this->state());
        $this->service($loader, $snapshot, ['running', 'no_changes'], true, ['en_GB', 'en_GB'])->synchronize();
    }

    /** @return array{source: string, attribute: string, deleted: string} */
    private function state(): array
    {
        return ['source' => hash('sha256', 'test|https://example.test|read|0|["en_GB"]'),
            'attribute' => 'old-attribute', 'deleted' => 'old-deletion'];
    }

    /** @param list<string> $expectedStatuses @param string[] $languageCodes */
    private function service(
        AttributeDefinitionLoader $loader,
        AttributeDefinitionSnapshotInterface $snapshot,
        array $expectedStatuses,
        bool $acquired = true,
        array $languageCodes = ['en_GB']
    ): AttributeDefinitionSynchronization {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getEnvironment')->willReturn('test');
        $config->method('getGraphQlUrl')->willReturn('https://example.test');
        $config->method('getMode')->willReturn('read');
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->method('lock')->willReturn($acquired);
        $lock->expects($acquired ? self::once() : self::never())->method('unlock');
        $recorder = $this->createMock(AttributeDefinitionCheckRecorderInterface::class);
        $index = 0;
        $recorder->expects(self::exactly(count($expectedStatuses)))->method('record')->willReturnCallback(
            static function (string $status) use ($expectedStatuses, &$index): void {
                self::assertSame($expectedStatuses[$index++], $status);
            }
        );

        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn($languageCodes);

        return new AttributeDefinitionSynchronization(
            $loader,
            $snapshot,
            $config,
            $lock,
            $this->createStub(ChangeReport::class),
            $recorder,
            $languages
        );
    }
}
