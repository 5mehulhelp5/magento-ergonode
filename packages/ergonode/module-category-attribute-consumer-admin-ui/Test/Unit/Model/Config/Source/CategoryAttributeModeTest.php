<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Test\Unit\Model\Config\Source;

use Ergonode\CategoryAttributeConsumerAdminUi\Model\Config\Source\CategoryAttributeMode;
use PHPUnit\Framework\TestCase;

class CategoryAttributeModeTest extends TestCase
{
    public function testExposesMappingAndManualModes(): void
    {
        $options = (new CategoryAttributeMode())->toOptionArray();

        self::assertSame(['mapping', 'manual'], array_column($options, 'value'));
    }
}
