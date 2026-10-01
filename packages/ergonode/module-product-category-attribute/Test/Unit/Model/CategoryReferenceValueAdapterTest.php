<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Test\Unit\Model;

use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttribute\Model\CategoryReferenceValueAdapter;
use PHPUnit\Framework\TestCase;

class CategoryReferenceValueAdapterTest extends TestCase
{
    public function testDirectionContributionsDoNotEnableEachOther(): void
    {
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturnCallback(static fn (string $code): bool => $code === 'category');
        $base = new CategoryReferenceValueAdapter($config);
        self::assertTrue($base->supports('category'));
        self::assertFalse($base->supports('old_category'));
        self::assertFalse($base->isAvailable('import'));
        self::assertFalse($base->isAvailable('publish'));
        $consumer = new CategoryReferenceValueAdapter($config, true);
        self::assertTrue($consumer->isAvailable('import'));
        self::assertFalse($consumer->isAvailable('publish'));
        $publisher = new CategoryReferenceValueAdapter($config, false, true);
        self::assertFalse($publisher->isAvailable('import'));
        self::assertTrue($publisher->isAvailable('publish'));
    }
}
