<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Unit\Controller\Adminhtml\Language;

use Ergonode\Language\Api\ErgonodeLanguageCodesRefresherInterface;
use Ergonode\Language\Api\LanguageSnapshotRemoverInterface;
use Ergonode\LanguageAdminUi\Controller\Adminhtml\Language\DeleteSnapshot;
use Ergonode\LanguageAdminUi\Controller\Adminhtml\Language\Refresh;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SnapshotActionsTest extends ControllerTestCase
{
    /** @param list<string> $codes */
    #[DataProvider('snapshots')]
    public function testRefreshReturnsTheActualSnapshotCount(array $codes): void
    {
        $service = $this->createMock(ErgonodeLanguageCodesRefresherInterface::class);
        $service->expects(self::once())->method('refreshErgonodeLanguageCodes')->willReturn($codes);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        (new Refresh($this->context([], [
            'success' => true, 'message' => 'Ergonode languages have been refreshed.', 'count' => count($codes),
        ]), $service, $logger))->execute();
    }

    /** @return array<string, array{list<string>}> */
    public static function snapshots(): array
    {
        return ['populated' => [['pl_PL', 'en_GB']], 'valid empty snapshot' => [[]]];
    }

    public function testDeleteDelegatesOnlyTheRequestedLanguage(): void
    {
        $service = $this->createMock(LanguageSnapshotRemoverInterface::class);
        $service->expects(self::once())->method('remove')->with('pl_PL');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        (new DeleteSnapshot($this->context(['code' => 'pl_PL'], [
            'success' => true, 'message' => 'The item has been removed from this list.',
        ]), $service, $logger))->execute();
    }

    public function testMissingCodeReturnsDomainValidationError(): void
    {
        $service = $this->createMock(LanguageSnapshotRemoverInterface::class);
        $service->expects(self::once())->method('remove')->with('')->willThrowException(
            new LocalizedException(__('Ergonode language code is required.'))
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        (new DeleteSnapshot($this->context([], [
            'success' => false, 'message' => 'Ergonode language code is required.',
        ]), $service, $logger))->execute();
    }

    #[DataProvider('failures')]
    public function testServiceFailuresReturnErrorsWithoutSuccess(bool $refresh, bool $unexpected): void
    {
        $exception = $unexpected
            ? new RuntimeException('Private transport or database detail.')
            : new LocalizedException(__('The snapshot operation was rejected.'));
        $service = $this->createMock($refresh
            ? ErgonodeLanguageCodesRefresherInterface::class
            : LanguageSnapshotRemoverInterface::class);
        $service->expects(self::once())
            ->method($refresh ? 'refreshErgonodeLanguageCodes' : 'remove')->willThrowException($exception);
        $logger = $this->createMock(LoggerInterface::class);
        if ($unexpected) {
            $logger->expects(self::once())->method('error')->with(
                $refresh
                    ? 'Unexpected error while refreshing Ergonode languages.'
                    : 'Unexpected error while deleting an Ergonode language snapshot.',
                ['exception' => $exception]
            );
        } else {
            $logger->expects(self::never())->method('error');
        }
        $genericMessage = $refresh
            ? 'Unable to refresh Ergonode languages. Check the Magento logs for details.'
            : 'Unable to remove the item from this list. Check the Magento logs for details.';
        $context = $this->context(['code' => 'pl_PL'], [
            'success' => false, 'message' => $unexpected ? $genericMessage : $exception->getMessage(),
        ]);
        ($refresh ? new Refresh($context, $service, $logger) : new DeleteSnapshot($context, $service, $logger))
            ->execute();
    }

    /** @return array<string, array{bool, bool}> */
    public static function failures(): array
    {
        return [
            'refresh validation' => [true, false], 'refresh unexpected' => [true, true],
            'delete validation' => [false, false], 'delete unexpected' => [false, true],
        ];
    }
}
