<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Test\Unit\Model\Config\Backend;

use Ergonode\CategoryConsumerHistoryAdminUi\Model\Config\Backend\RetentionDays;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RetentionDaysTest extends TestCase
{
    #[DataProvider('acceptedValues')]
    public function testSavesPositiveRetentionDays(string $value, int $expected): void
    {
        $model = $this->model();
        $model->setValue($value);
        $model->beforeSave();

        self::assertSame($expected, $model->getValue());
    }

    public static function acceptedValues(): array
    {
        return [['1', 1], ['30', 30], [' 90 ', 90], ['365', 365]];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValuesBeforeSaving(string $value): void
    {
        $model = $this->model();
        $model->setValue($value);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('History retention must be a positive whole number of days.');
        $model->beforeSave();
    }

    public static function invalidValues(): array
    {
        return [[''], ['0'], ['-1'], ['30.5'], ['abc'], ['3e1']];
    }

    private function model(): RetentionDays
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return new RetentionDays(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
    }
}
