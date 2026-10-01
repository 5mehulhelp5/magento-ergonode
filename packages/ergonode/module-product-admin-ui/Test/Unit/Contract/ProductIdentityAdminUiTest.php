<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Contract;

use Ergonode\ProductAdminUi\Model\Config\Source\MagentoIdentityAttribute;
use DOMDocument;
use DOMXPath;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use PHPUnit\Framework\TestCase;

class ProductIdentityAdminUiTest extends TestCase
{
    public function testMappedAttributeSelectorIsConditional(): void
    {
        $root = dirname(__DIR__, 3);
        $document = new DOMDocument();
        self::assertTrue($document->load($root . '/etc/adminhtml/system.xml'));
        $xpath = new DOMXPath($document);

        self::assertSame(1, $xpath->query(
            '//field[@id="magento_attribute" and @type="select"]'
            . '/depends/field[@id="sku_mode" and text()="mapped"]'
        )->length);
        self::assertSame(1, $xpath->query(
            '//field[@id="magento_attribute"]/source_model'
            . '[text()="' . MagentoIdentityAttribute::class . '"]'
        )->length);
        self::assertSame(1, $xpath->query(
            '//field[@id="assigned_readiness" and @type="note"]'
            . '/depends/field[@id="sku_mode" and text()="assigned"]'
        )->length);
    }

    public function testProductGridExposesFilterableErgonodeSkuColumn(): void
    {
        $root = dirname(__DIR__, 3);
        $document = new DOMDocument();
        self::assertTrue($document->load($root . '/view/adminhtml/ui_component/product_listing.xml'));
        $xpath = new DOMXPath($document);

        self::assertSame(1, $xpath->query('//columns[@name="product_columns"]/column[@name="ergonode_sku"]')->length);
        self::assertSame(1, $xpath->query('//column[@name="ergonode_sku"]/settings/addField[text()="true"]')->length);
        self::assertSame(1, $xpath->query('//column[@name="ergonode_sku"]/settings/filter[text()="text"]')->length);
    }

    public function testProductDataProviderUsesDedicatedFieldAndFilterStrategies(): void
    {
        $root = dirname(__DIR__, 3);
        $document = new DOMDocument();
        self::assertTrue($document->load($root . '/etc/adminhtml/di.xml'));
        $xpath = new DOMXPath($document);
        $provider = ProductDataProvider::class;

        self::assertSame(
            1,
            $xpath->query(
                '//type[@name="' . $provider . '"]/arguments/'
                . 'argument[@name="addFieldStrategies"]/item[@name="ergonode_sku"]'
            )->length
        );
        self::assertSame(
            1,
            $xpath->query(
                '//type[@name="' . $provider . '"]/arguments/'
                . 'argument[@name="addFilterStrategies"]/item[@name="ergonode_sku"]'
            )->length
        );
    }
}
