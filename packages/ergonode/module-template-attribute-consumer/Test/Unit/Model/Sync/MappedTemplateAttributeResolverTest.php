<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Sync\MappedTemplateAttributeResolver;
use PHPUnit\Framework\TestCase;

class MappedTemplateAttributeResolverTest extends TestCase
{
    public function testReturnsOnlyCompleteMappingsToExistingMagentoAttributes(): void
    {
        self::assertSame([
            'mapped' => [
                [
                    'ergonode_code' => 'color',
                    'magento_code' => 'catalog_color',
                    'attribute_id' => 41,
                    'sort_order' => 1,
                ],
            ],
            'skipped' => [
                [
                    'ergonode_code' => 'unmapped',
                    'magento_code' => null,
                    'reason' => MappedTemplateAttributeResolver::SKIP_INCOMPLETE_MAPPING,
                ],
                [
                    'ergonode_code' => 'missing_in_magento',
                    'magento_code' => 'removed_attribute',
                    'reason' => MappedTemplateAttributeResolver::SKIP_INCOMPLETE_MAPPING,
                ],
            ],
        ], (new MappedTemplateAttributeResolver())->resolve(
            [
                ['code' => 'color', 'sort_order' => 0],
                ['code' => 'unmapped', 'sort_order' => 2],
                ['code' => 'missing_in_magento', 'sort_order' => 3],
            ],
            [
                'color' => 'catalog_color',
                'missing_in_magento' => 'removed_attribute',
            ],
            ['catalog_color' => 41]
        ));
    }

    public function testSeparatesProtectedSystemAttributeFromIncompleteMapping(): void
    {
        $result = (new MappedTemplateAttributeResolver())->resolve(
            [['code' => 'remote_price', 'sort_order' => 10]],
            ['remote_price' => 'price'],
            [],
            ['price' => true]
        );

        self::assertSame(
            MappedTemplateAttributeResolver::SKIP_SYSTEM_ATTRIBUTE,
            $result['skipped'][0]['reason']
        );
    }
}
