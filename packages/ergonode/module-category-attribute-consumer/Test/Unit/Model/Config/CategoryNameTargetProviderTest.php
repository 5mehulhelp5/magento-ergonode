<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Config;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryNameTargetProvider;
use Ergonode\CategoryAttributeConsumer\Model\Provider\CategoryNameAttributeProvider;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Config\CategoryNameTargetProvider as DefaultTargetProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryNameTargetProviderTest extends TestCase
{
    public function testFieldModeSelectsTheMagentoTextDestination(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('getNameMode')->willReturn('attribute');
        $default = $this->createMock(DefaultTargetProvider::class);
        $default->expects(self::never())->method('getAttributeCode');
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturn('meta_title');
        $attributes = $this->createStub(CategoryNameAttributeProvider::class);
        $attributes->method('getAttributes')->willReturn(['meta_title' => 'Page title']);

        self::assertSame('meta_title', (new CategoryNameTargetProvider($config, $default, $scope, $attributes))
            ->getAttributeCode());
    }

    public function testAnUnavailableFieldCannotBeWritten(): void
    {
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('getNameMode')->willReturn('attribute');
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturn('description');
        $attributes = $this->createStub(CategoryNameAttributeProvider::class);
        $attributes->method('getAttributes')->willReturn(['name' => 'Name']);

        $this->expectException(LocalizedException::class);
        (new CategoryNameTargetProvider(
            $config,
            $this->createStub(DefaultTargetProvider::class),
            $scope,
            $attributes
        ))->getAttributeCode();
    }
}
