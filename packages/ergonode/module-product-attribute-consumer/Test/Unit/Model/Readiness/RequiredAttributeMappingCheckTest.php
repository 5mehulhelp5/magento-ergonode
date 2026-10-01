<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Readiness;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttributeConsumer\Model\Readiness\RequiredAttributeMappingCheck;
use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Model\Data\ReadinessContext;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use PHPUnit\Framework\TestCase;

class RequiredAttributeMappingCheckTest extends TestCase
{
    public function testReportsOnlyUnmappedRequiredAttributes(): void
    {
        $attributeProvider = $this->createStub(MagentoAttributeProvider::class);
        $attributeProvider->method('getAttributes')->willReturn(
            [
            ['code' => 'name', 'label' => 'Name', 'required' => true],
            ['code' => 'color', 'label' => 'Color', 'required' => false],
            ['code' => 'url_key', 'label' => 'URL Key', 'required' => true],
            ]
        );
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn(
            [
            ['magento_attribute_code' => 'name'],
            ]
        );
        $check = new RequiredAttributeMappingCheck(
            $attributeProvider,
            $mappingProvider,
            new ReadinessIssueFactory()
        );

        $issues = $check->check(new ReadinessContext(ReadinessContextInterface::OPERATION_OVERVIEW));

        self::assertCount(1, $issues);
        self::assertSame(['URL Key (url_key)'], $issues[0]->getDetails());
        self::assertSame('blocker', $issues[0]->getSeverity());
    }
}
