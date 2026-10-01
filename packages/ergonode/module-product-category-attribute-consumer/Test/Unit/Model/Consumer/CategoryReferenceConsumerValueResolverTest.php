<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeConsumer\Test\Unit\Model\Consumer;

use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttributeConsumer\Model\Consumer\CategoryReferenceCategoryIdResolver;
use Ergonode\ProductCategoryAttributeConsumer\Model\Consumer\CategoryReferenceConsumerValueResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryReferenceConsumerValueResolverTest extends TestCase
{
    public function testResolvesErgonodeCategoryCodeForTargetStore(): void
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturn(true);
        $categoryIdsResolver = $this->createMock(CategoryReferenceCategoryIdResolver::class);
        $categoryIdsResolver->expects(self::once())
            ->method('resolve')
            ->with('chairs', 2)
            ->willReturn(42);
        $resolver = new CategoryReferenceConsumerValueResolver($config, $categoryIdsResolver);
        $mapping = [
            'magento_attribute_code' => 'default_category',
            'ergonode_attribute_code' => 'default_category',
            'ergonode_type' => 'text',
        ];

        self::assertTrue($resolver->supports($mapping));
        self::assertSame(42, $resolver->resolve('chairs', $mapping, 'pl_PL', 2));
    }

    public function testRejectsMissingRequiredAdminValue(): void
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isRequired')->willReturn(true);
        $resolver = new CategoryReferenceConsumerValueResolver(
            $config,
            $this->createStub(CategoryReferenceCategoryIdResolver::class)
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Required Ergonode category reference is missing');
        $resolver->resolve(
            null,
            ['ergonode_attribute_code' => 'default_category', 'ergonode_type' => 'text'],
            'pl_PL',
            0
        );
    }

    public function testRejectsWhitespaceRequiredAdminValue(): void
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isRequired')->willReturn(true);
        $resolver = new CategoryReferenceConsumerValueResolver(
            $config,
            $this->createStub(CategoryReferenceCategoryIdResolver::class)
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Required Ergonode category reference is missing');
        $resolver->resolve(
            '   ',
            ['ergonode_attribute_code' => 'default_category', 'ergonode_type' => 'text'],
            'pl_PL',
            0
        );
    }
}
