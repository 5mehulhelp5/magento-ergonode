<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Source;

use Ergonode\ProductPublisher\Model\Source\DefaultProductTemplateCodeProvider;
use PHPUnit\Framework\TestCase;

class DefaultProductTemplateCodeProviderTest extends TestCase
{
    public function testReturnsDefaultTemplateForEveryValidAttributeSet(): void
    {
        self::assertSame(
            [4 => 'default', 17 => 'default'],
            (new DefaultProductTemplateCodeProvider())->getTemplateCodesByAttributeSetIds([17, 4, 17, 0])
        );
    }
}
