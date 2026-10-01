<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeAdminUi\Test\Unit\Plugin;

use Ergonode\ProductAttributeAdminUi\Block\Adminhtml\Attribute\Mapping;
use Ergonode\ProductCategoryAttributeAdminUi\Plugin\MappingBlockPlugin;
use PHPUnit\Framework\TestCase;

class MappingBlockPluginTest extends TestCase
{
    public function testMarksCategoryReferenceInAttributeLabel(): void
    {
        $result = (new MappingBlockPlugin())->afterGetMagentoAttributes(
            $this->createStub(Mapping::class),
            [['label' => 'Default Category', 'code' => 'default_category', 'category_reference' => true]]
        );

        self::assertSame('Default Category · Category reference', $result[0]['label']);
    }
}
