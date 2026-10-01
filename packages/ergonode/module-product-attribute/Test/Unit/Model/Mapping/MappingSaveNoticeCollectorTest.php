<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\MappingSaveNoticeCollector;
use PHPUnit\Framework\TestCase;

class MappingSaveNoticeCollectorTest extends TestCase
{
    public function testConsumesUniqueNonEmptyWarningsOnce(): void
    {
        $collector = new MappingSaveNoticeCollector();
        $collector->addWarning(' Attribute already exists. ');
        $collector->addWarning('Attribute already exists.');
        $collector->addWarning('');

        self::assertSame(['Attribute already exists.'], $collector->consumeWarnings());
        self::assertSame([], $collector->consumeWarnings());
    }
}
