<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisherAdminUi\Test\Unit\Contract;

use DOMDocument;
use DOMXPath;
use Ergonode\ProductCategoryPublisherAdminUi\Model\Config\Source\CategoryPublicationMode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CategoryPublicationAdminUiTest extends TestCase
{
    public function testCategoryPublicationUsesShortUserFacingModes(): void
    {
        $root = dirname((new ReflectionClass(CategoryPublicationMode::class))->getFileName() ?: '', 4);
        $document = new DOMDocument();
        self::assertTrue($document->load($root . '/etc/adminhtml/system.xml'));
        $xpath = new DOMXPath($document);
        $sourceModel = CategoryPublicationMode::class;

        self::assertSame(1, $xpath->query(
            '//section[@id="ergonode_products"]/group[@id="publication"][label="Publication"]'
            . '/field[@id="category_mode"]/label[text()="Categories"]'
        )->length);
        self::assertSame(1, $xpath->query(
            '//field[@id="category_mode"]'
            . '/source_model[text()="' . $sourceModel . '"]'
        )->length);

        self::assertSame(['Keep existing', 'Match Magento'], array_map(
            static fn (array $option): string => (string)$option['label'],
            (new CategoryPublicationMode())->toOptionArray()
        ));
    }
}
