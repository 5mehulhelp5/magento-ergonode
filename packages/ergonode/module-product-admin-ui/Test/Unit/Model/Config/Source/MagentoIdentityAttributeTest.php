<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Model\Config\Source;

use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductAdminUi\Model\Config\Source\MagentoIdentityAttribute;
use PHPUnit\Framework\TestCase;

class MagentoIdentityAttributeTest extends TestCase
{
    public function testSelectUsesEligibleCodesFromSharedIdentityPolicy(): void
    {
        $identityAttribute = $this->createMock(MagentoIdentityAttributeInterface::class);
        $identityAttribute->expects(self::once())->method('getEligibleAttributes')->willReturn([
            'navireo_id' => 'Navireo ID',
        ]);

        $options = (new MagentoIdentityAttribute($identityAttribute))->toOptionArray();

        self::assertSame(['', 'navireo_id'], array_column($options, 'value'));
        self::assertSame('Navireo ID (navireo_id)', $options[1]['label']);
    }
}
