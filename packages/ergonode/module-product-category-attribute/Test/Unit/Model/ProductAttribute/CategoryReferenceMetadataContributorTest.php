<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Test\Unit\Model\ProductAttribute;

use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttribute\Model\ProductAttribute\CategoryReferenceMetadataContributor;
use PHPUnit\Framework\TestCase;

class CategoryReferenceMetadataContributorTest extends TestCase
{
    public function testContributesConfiguredHiddenAttributeMetadata(): void
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('getAttributeCode')->willReturn('default_category');
        $config->method('isConfigured')->with('default_category')->willReturn(true);
        $contributor = new CategoryReferenceMetadataContributor($config);

        self::assertSame(['default_category'], $contributor->getAdditionalAttributeCodes());
        self::assertTrue($contributor->contribute(['code' => 'default_category'])['category_reference']);
    }
}
