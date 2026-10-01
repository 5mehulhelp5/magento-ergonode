<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Test\Unit\Model;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeManagementProviderInterface;
use Ergonode\CategoryAttributeConsumerAdminUi\Model\CategoryOptionContextResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryOptionContextResolverTest extends TestCase
{
    public function testReturnsAttributeCodeFromSavedMapping(): void
    {
        $mappingProvider = $this->createMock(CategoryAttributeManagementProviderInterface::class);
        $mappingProvider->expects(self::once())
            ->method('getAttributeMappingRow')
            ->with(7)
            ->willReturn(['ergonode_attribute_code' => ' category_color ']);

        self::assertSame(
            'category_color',
            (new CategoryOptionContextResolver($mappingProvider))->getAttributeCode(7)
        );
    }

    #[DataProvider('invalidMappingProvider')]
    public function testRejectsMissingAttributeContext(array $mapping): void
    {
        $mappingProvider = $this->createStub(CategoryAttributeManagementProviderInterface::class);
        $mappingProvider->method('getAttributeMappingRow')->willReturn($mapping);

        $this->expectException(LocalizedException::class);
        (new CategoryOptionContextResolver($mappingProvider))->getAttributeCode(7);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function invalidMappingProvider(): array
    {
        return [
            'missing mapping' => [[]],
            'missing attribute code' => [[]],
            'blank attribute code' => [['ergonode_attribute_code' => '  ']],
        ];
    }
}
