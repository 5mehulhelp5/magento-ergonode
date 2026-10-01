<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;

use Ergonode\Category\Model\Import\CategoryTreeSourceChecker;
use Ergonode\Category\Model\Import\MissingCategoryTreeException;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CategoryTreeSourceCheckerTest extends TestCase
{
    public function testConfirmedAbsenceIsPersistedAndRecoveryRequiresCompleteSnapshot(): void
    {
        $stored = null;
        $flags = $this->createStub(FlagManager::class);
        $flags->method('getFlagData')->willReturnCallback(static function () use (&$stored) {
            return $stored;
        });
        $flags->method('saveFlag')->willReturnCallback(static function (string $key, array $data) use (&$stored): bool {
            $stored = $data;

            return true;
        });
        $state = new CategoryTreeSourceState($flags);
        $state->record(7, CategoryTreeSourceState::AVAILABLE, snapshot: true);
        $snapshotAt = $state->get(7)['snapshot_at'];
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturnOnConsecutiveCalls(
            ['categoryTree' => null],
            ['categoryTree' => ['code' => 'default']]
        );
        $checker = new CategoryTreeSourceChecker(
            $client,
            $state,
            new CategoryTreeDownloadScope()
        );
        try {
            $checker->check(7, 'default');
            self::fail('A missing tree must block its synchronization.');
        } catch (MissingCategoryTreeException $exception) {
            self::assertStringContainsString('preserved', $exception->getMessage());
        }
        self::assertSame(CategoryTreeSourceState::MISSING, (new CategoryTreeSourceState($flags))->get(7)['status']);
        self::assertSame($snapshotAt, $state->get(7)['snapshot_at']);
        $checker->check(7, 'default');
        self::assertTrue($state->get(7)['requires_refresh']);
        $state->record(7, CategoryTreeSourceState::AVAILABLE, snapshot: true);
        self::assertFalse($state->get(7)['requires_refresh']);
    }

    public function testTransportFailureIsNotRecordedAsDeletion(): void
    {
        $failure = new RuntimeException('Connection failed');
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willThrowException($failure);
        $state = $this->createMock(CategoryTreeSourceState::class);
        $state->expects(self::once())->method('record')->with(7, CategoryTreeSourceState::UNAVAILABLE);

        $this->expectExceptionObject($failure);
        (new CategoryTreeSourceChecker(
            $client,
            $state,
            new CategoryTreeDownloadScope()
        ))->check(7, 'default');
    }

    public function testMalformedSuccessfulResponseIsNotEvidenceOfDeletion(): void
    {
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturn([]);
        $state = $this->createMock(CategoryTreeSourceState::class);
        $state->expects(self::once())->method('record')->with(7, CategoryTreeSourceState::UNAVAILABLE);

        $this->expectException(LocalizedException::class);
        (new CategoryTreeSourceChecker(
            $client,
            $state,
            new CategoryTreeDownloadScope()
        ))->check(7, 'default');
    }
}
