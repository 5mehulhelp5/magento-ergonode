<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Test\Unit\Model\Config;

use Ergonode\MediaAdminUi\Model\Config\Backend\UnmanagedImages as Backend;
use Ergonode\MediaAdminUi\Model\Config\Source\UnmanagedImages as Source;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UnmanagedImagesTest extends TestCase
{
    public function testPanelOffersAllThreeModesWithKeepFirst(): void
    {
        self::assertSame(['keep', 'hide', 'remove'], array_column((new Source())->toOptionArray(), 'value'));
    }

    #[DataProvider('valid')]
    public function testGlobalSettingUsesTheSameNormalizationAsRuntime(mixed $value, string $expected): void
    {
        $backend = $this->backend();
        $backend->setData('scope', 'default')->setValue($value);
        self::assertSame($backend, $backend->beforeSave());
        self::assertSame($expected, $backend->getValue());
    }

    public static function valid(): array
    {
        return [[null, 'keep'], ['', 'keep'], ['keep', 'keep'], [' hide ', 'hide'], ['remove', 'remove']];
    }

    #[DataProvider('invalid')]
    public function testRejectsInvalidOrScopedSettings(string $scope, mixed $value): void
    {
        $backend = $this->backend();
        $backend->setData('scope', $scope)->setValue($value);
        $this->expectException(LocalizedException::class);
        $backend->beforeSave();
    }

    public static function invalid(): array
    {
        return [['websites', 'remove'], ['stores', 'hide'], ['default', 'invalid'], ['default', ['remove']]];
    }

    private function backend(): Backend
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        return new Backend($context, $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class), $this->createStub(TypeListInterface::class));
    }
}
