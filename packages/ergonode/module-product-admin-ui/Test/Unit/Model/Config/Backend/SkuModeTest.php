<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Model\Config\Backend;

use Ergonode\Product\Api\AssignedIdentitySupportInterface;
use Ergonode\Product\Model\Config\ProductIdentityModeProvider;
use Ergonode\ProductAdminUi\Model\Config\Backend\SkuMode;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class SkuModeTest extends TestCase
{
    public function testCannotSaveAssignedModeWithoutExtension(): void
    {
        $model = $this->model(false);
        $model->setValue('assigned');
        $this->expectException(LocalizedException::class);
        $model->beforeSave();
    }

    public function testCanSelectAssignedModeBeforeConfiguringItsMapping(): void
    {
        $model = $this->model(true);
        $model->setValue('assigned');
        $model->beforeSave();

        self::assertSame('assigned', $model->getValue());
    }

    public function testCannotSaveHistoricalSharedModeForNewBindings(): void
    {
        $model = $this->model(true);
        $model->setValue('shared');

        $this->expectException(LocalizedException::class);
        $model->beforeSave();
    }

    private function model(bool $supported): SkuMode
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $config = $this->createStub(ScopeConfigInterface::class);
        $supports = [];
        if ($supported) {
            $support = $this->createMock(AssignedIdentitySupportInterface::class);
            $support->expects(self::never())->method('validate');
            $supports[] = $support;
        }

        return new SkuMode(
            $context,
            $this->createStub(Registry::class),
            $config,
            $this->createStub(TypeListInterface::class),
            new ProductIdentityModeProvider($config, $supports)
        );
    }
}
