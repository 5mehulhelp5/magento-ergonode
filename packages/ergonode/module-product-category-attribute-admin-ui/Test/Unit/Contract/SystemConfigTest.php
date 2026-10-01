<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeAdminUi\Test\Unit\Contract;

use DOMDocument;
use DOMXPath;
use Ergonode\ProductAttributeAdminUi\Model\Config\Source\ProductAttributeCode;
use PHPUnit\Framework\TestCase;

class SystemConfigTest extends TestCase
{
    public function testOwnsDefaultCategoryAttributeField(): void
    {
        $document = new DOMDocument();
        self::assertTrue($document->load(dirname(__DIR__, 3) . '/etc/adminhtml/system.xml'));
        $xpath = new DOMXPath($document);
        $source = (string)$xpath->evaluate('string(//field[@id="default_category_attribute"]/source_model)');
        self::assertSame(ProductAttributeCode::class, $source);
        self::assertTrue(class_exists($source));

        self::assertSame(1, $xpath->query(
            '//section[@id="ergonode_products"]/group[@id="attributes"]'
            . '/field[@id="default_category_attribute"]'
        )->length);
    }
}
