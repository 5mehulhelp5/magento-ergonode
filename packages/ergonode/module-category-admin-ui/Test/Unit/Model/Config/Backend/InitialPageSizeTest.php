<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Test\Unit\Model\Config\Backend;

use Ergonode\CategoryAdminUi\Model\Config\Backend\InitialPageSize;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InitialPageSizeTest extends TestCase
{
    #[DataProvider('acceptedValues')]
    public function testSavesAnIntegerWithinTheAllowedRange(string $value, int $expected): void
    {
        $model = $this->model();
        $model->setValue($value);
        $model->beforeSave();

        self::assertSame($expected, $model->getValue());
    }

    public static function acceptedValues(): array
    {
        return [['100', 100], ['700', 700], [' 750 ', 750], ['1000', 1000]];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValuesBeforeSaving(string $value): void
    {
        $model = $this->model();
        $model->setValue($value);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Limit/req must be an integer from 100 to 1000.');
        $model->beforeSave();
    }

    public static function invalidValues(): array
    {
        return [[''], ['99'], ['1001'], ['-1'], ['700.5'], ['abc'], ['7e2']];
    }

    private function model(): InitialPageSize
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return new InitialPageSize(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
    }
}
