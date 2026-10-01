<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Model\Data\ReadinessContext;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use Ergonode\Template\Api\TemplateAttributeSetMappingProviderInterface;
use Ergonode\TemplateConsumer\Model\Readiness\ProductAttributeSetUsageProvider;
use Ergonode\TemplateConsumer\Model\Readiness\TemplateMappingCheck;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;
use PHPUnit\Framework\TestCase;

class TemplateMappingCheckTest extends TestCase
{
    /** Verify that each omitted attribute set is visible without blocking synchronization. */
    public function testReportsEveryUnmappedUsedAttributeSetAsSeparateWarning(): void
    {
        $mappingProvider = $this->createMock(TemplateAttributeSetMappingProviderInterface::class);
        $mappingProvider->expects($this->once())
            ->method('getTemplateCodesByAttributeSetIds')
            ->with([4, 7, 9])
            ->willReturn([4 => 'default']);
        $templateProvider = $this->createStub(TemplateCacheProvider::class);
        $templateProvider->method('getTemplatesWithAttributeSet')->willReturn([[
            'entity_id' => 1,
            'code' => 'default',
            'attribute_set_id' => 4,
        ]]);
        $usageProvider = $this->createMock(ProductAttributeSetUsageProvider::class);
        $usageProvider->expects($this->once())
            ->method('getUsage')
            ->with(['SKU-1'])
            ->willReturn([4 => 3, 7 => 1, 9 => 6]);
        $check = new TemplateMappingCheck(
            $mappingProvider,
            $templateProvider,
            $usageProvider,
            new ReadinessIssueFactory()
        );

        $issues = $check->check(new ReadinessContext(
            ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS,
            ['products' => ['SKU-1']]
        ));

        self::assertCount(2, $issues);
        self::assertSame(
            [
                'Attribute set ID 7 (1 product(s)) does not have an Ergonode template mapping. '
                    . 'Products using this attribute set will be skipped.',
                'Attribute set ID 9 (6 product(s)) does not have an Ergonode template mapping. '
                    . 'Products using this attribute set will be skipped.',
            ],
            array_map(static fn (ReadinessIssueInterface $issue): string => $issue->getMessage(), $issues)
        );
        foreach ($issues as $issue) {
            self::assertSame(ReadinessIssueInterface::SEVERITY_WARNING, $issue->getSeverity());
            self::assertSame('templates.product_attribute_set_mapping_missing', $issue->getCode());
            self::assertSame([], $issue->getDetails());
        }
    }

    /** Verify that synchronization still requires at least one template mapping. */
    public function testStillBlocksWhenNoTemplateIsMapped(): void
    {
        $mappingProvider = $this->createStub(TemplateAttributeSetMappingProviderInterface::class);
        $templateProvider = $this->createStub(TemplateCacheProvider::class);
        $templateProvider->method('getTemplatesWithAttributeSet')->willReturn([]);
        $usageProvider = $this->createStub(ProductAttributeSetUsageProvider::class);
        $usageProvider->method('getUsage')->willReturn([7 => 1]);
        $check = new TemplateMappingCheck(
            $mappingProvider,
            $templateProvider,
            $usageProvider,
            new ReadinessIssueFactory()
        );

        $issues = $check->check(new ReadinessContext(ReadinessContextInterface::OPERATION_OVERVIEW));

        self::assertCount(2, $issues);
        self::assertSame('templates.mapping_missing', $issues[0]->getCode());
        self::assertSame(ReadinessIssueInterface::SEVERITY_BLOCKER, $issues[0]->getSeverity());
        self::assertSame('templates.product_attribute_set_mapping_missing', $issues[1]->getCode());
        self::assertSame(ReadinessIssueInterface::SEVERITY_WARNING, $issues[1]->getSeverity());
    }
}
