<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\CategoryConsumer\Model\Sync\CategoryStructureSynchronizationProcess;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryStreamEligibilityTest extends TestCase
{
    #[DataProvider('eligibilityCases')]
    public function testSharedPolicy(
        bool $enabled,
        bool $dataEnabled,
        bool $active,
        bool $data,
        ?string $reason
    ): void {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isDataSynchronizationEnabled')->willReturn($dataEnabled);
        $config->method('isCronEnabled')->willReturn(true);
        $config->method('isDataCronEnabled')->willReturn(true);
        $trees = $this->createStub(CategoryTreeReadinessProviderInterface::class);
        $trees->method('getActiveTrees')->willReturn($active ? [[
            'category_tree_id' => 1,
            'tree_code' => 'main',
            'root_category_id' => 2,
            'root_exists' => true,
        ]] : []);
        $eligibility = new CategoryStreamEligibility(
            $config,
            $trees,
            $this->createStub(CategoryCreationConfigurationProviderInterface::class)
        );

        self::assertSame($reason, $eligibility->getBlockingReason($data)?->render());
        self::assertSame($reason === null, $eligibility->canRunCron($data));
        if ($reason !== null) {
            $this->expectException(LocalizedException::class);
            $this->expectExceptionMessage($reason);
        }
        $eligibility->assertCanSynchronize($data);
    }

    public function testMissingCreationMappingsBlockStructureAndCronButNotExistingCategoryData(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $config->method('isCronEnabled')->willReturn(true);
        $trees = $this->createStub(CategoryTreeReadinessProviderInterface::class);
        $trees->method('getActiveTrees')->willReturn([['category_tree_id' => 3]]);
        $creation = $this->createStub(CategoryCreationConfigurationProviderInterface::class);
        $creation->method('get')->willThrowException(new LocalizedException(__('Missing mapping: %1', 'is_active')));
        $eligibility = new CategoryStreamEligibility($config, $trees, $creation);

        self::assertSame('Missing mapping: is_active', (string)$eligibility->getBlockingReason());
        self::assertFalse($eligibility->canRunCron());
        self::assertNull($eligibility->getBlockingReason(data: true));
        $importer = $this->createMock(CategoryTreeStreamImporter::class);
        $importer->expects(self::never())->method('execute');
        $process = new CategoryStructureSynchronizationProcess(
            $eligibility,
            $this->createStub(CategorySynchronizationLock::class),
            $importer,
            $this->createStub(ChangeReport::class),
            new CategoryTreeDownloadScope()
        );
        $this->expectExceptionMessage('Missing mapping: is_active');
        $process->execute(true);
    }

    /** @return iterable<string, array{bool, bool, bool, bool, string|null}> */
    public static function eligibilityCases(): iterable
    {
        yield 'structure ignores data switch' => [true, false, true, false, null];
        yield 'data enabled' => [true, true, true, true, null];
        foreach ([false, true] as $data) {
            $stream = $data ? 'data' : 'structure';
            yield $stream . ' integration disabled' => [
                false, true, true, $data, 'Ergonode category synchronization is disabled.',
            ];
            yield $stream . ' no active mapping' => [
                true, true, false, $data, 'Enable at least one category tree mapping before synchronizing.',
            ];
        }
        yield 'data disabled' => [true, false, true, true, 'Category data synchronization is disabled.'];
    }

    public function testCronSchedulesAreIndependentAndDoNotBlockManualSynchronization(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $config->method('isCronEnabled')->willReturn(false);
        $config->method('isDataCronEnabled')->willReturn(true);
        $trees = $this->createStub(CategoryTreeReadinessProviderInterface::class);
        $trees->method('getActiveTrees')->willReturn([[
            'category_tree_id' => 1,
            'tree_code' => 'main',
            'root_category_id' => 2,
            'root_exists' => true,
        ]]);
        $eligibility = new CategoryStreamEligibility(
            $config,
            $trees,
            $this->createStub(CategoryCreationConfigurationProviderInterface::class)
        );

        self::assertFalse($eligibility->canRunCron());
        self::assertTrue($eligibility->canRunCron(data: true));
        $eligibility->assertCanSynchronize();
        $eligibility->assertCanSynchronize(data: true);
    }
}
