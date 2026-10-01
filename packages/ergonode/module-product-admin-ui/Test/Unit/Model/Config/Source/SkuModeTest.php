<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Model\Config\Source;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductAdminUi\Model\Config\Source\SkuMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SkuModeTest extends TestCase
{
    /** @param string[] $expected */
    #[DataProvider('modes')]
    public function testOptionsRespectInstalledSupport(bool $supported, string $current, array $expected): void
    {
        $provider = $this->createStub(ProductIdentityModeProviderInterface::class);
        $provider->method('isAssignedModeAvailable')->willReturn($supported);
        $provider->method('getMode')->willReturn($current);
        $options = (new SkuMode($provider))->toOptionArray();

        self::assertSame($expected, array_column($options, 'value'));
        if (!$supported && $current === 'assigned') {
            self::assertStringContainsString('unavailable', (string)$options[2]['label']);
        }
    }

    /** @return array<string, array{bool, string, string[]}> */
    public static function modes(): array
    {
        return [
            'base only' => [false, 'shared', ['', 'mapped']],
            'attribute support' => [true, 'shared', ['', 'mapped', 'assigned']],
            'removed support preserves selection' => [false, 'assigned', ['', 'mapped', 'assigned']],
        ];
    }
}
