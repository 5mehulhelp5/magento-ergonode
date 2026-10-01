<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Readiness;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Model\Readiness\CategoryTreeMappingCheck;
use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Model\Data\ReadinessContext;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryTreeMappingCheckTest extends TestCase
{
    public function testSupportsOverviewButNotProductPublication(): void
    {
        $check = $this->check($this->createStub(CategoryTreeReadinessProviderInterface::class));

        self::assertTrue($check->supports(ReadinessContextInterface::OPERATION_OVERVIEW));
        self::assertFalse($check->supports(ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS));
    }

    public function testActiveTreeWithFixedCreationValuesIsReadyWithoutExistingCategoryMappings(): void
    {
        $check = $this->check($this->activeTreeProvider());

        $issues = $check->check(new ReadinessContext(ReadinessContextInterface::OPERATION_OVERVIEW));

        self::assertSame([], $issues);
    }

    public function testMappedCreationValuesRequireEnabledAttributeSynchronization(): void
    {
        $configuration = $this->createStub(CategoryCreationConfigurationProviderInterface::class);
        $configuration->method('get')->willThrowException(new LocalizedException(__(
            'Enable category attribute synchronization or configure fixed values.'
        )));
        $check = $this->check($this->activeTreeProvider(), $configuration);

        $issues = $check->check(new ReadinessContext(ReadinessContextInterface::OPERATION_OVERVIEW));

        self::assertCount(1, $issues);
        self::assertSame('categories.category_mapping_missing', $issues[0]->getCode());
        self::assertSame('blocker', $issues[0]->getSeverity());
    }

    private function check(
        CategoryTreeReadinessProviderInterface $treeProvider,
        ?CategoryCreationConfigurationProviderInterface $configuration = null
    ): CategoryTreeMappingCheck {
        if ($configuration === null) {
            $configuration = $this->createStub(CategoryCreationConfigurationProviderInterface::class);
            $configuration->method('get')->willReturn([
                'attributes_enabled' => false,
                'fixed_values' => ['is_active' => 1, 'include_in_menu' => 1],
                'mapped_attribute_codes' => [],
            ]);
        }

        return new CategoryTreeMappingCheck(
            $treeProvider,
            new ReadinessIssueFactory(),
            $configuration
        );
    }

    private function activeTreeProvider(): CategoryTreeReadinessProviderInterface
    {
        $provider = $this->createStub(CategoryTreeReadinessProviderInterface::class);
        $provider->method('getActiveTrees')->willReturn([[
            'category_tree_id' => 1,
            'tree_code' => 'master',
            'root_category_id' => 2,
            'root_exists' => true,
        ]]);

        return $provider;
    }
}
