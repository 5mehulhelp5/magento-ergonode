<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Config\Backend;

use Ergonode\CoreAdminUi\Model\Config\Backend\RequestsPerMinute;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestsPerMinuteTest extends TestCase
{
    public function testAcceptsZeroAndPositiveIntegerLimits(): void
    {
        foreach (['0', '10'] as $value) {
            $model = $this->model();
            $model->setValue($value);
            $model->beforeSave();
            self::assertSame($value, $model->getValue());
        }
    }

    #[DataProvider('invalidLimits')]
    public function testRejectsInvalidLimits(string $value): void
    {
        $model = $this->model();
        $model->setValue($value);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('must be a non-negative integer');
        $model->beforeSave();
    }

    /** @return array<string, array{string}> */
    public static function invalidLimits(): array
    {
        return [
            'negative' => ['-1'],
            'fractional' => ['1.5'],
            'text' => ['abc'],
            'empty' => [''],
            'overflow' => [str_repeat('9', 30)],
        ];
    }

    private function model(): RequestsPerMinute
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        return new RequestsPerMinute(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
    }
}
