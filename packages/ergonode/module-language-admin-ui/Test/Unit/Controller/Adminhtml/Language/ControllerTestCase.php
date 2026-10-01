<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Unit\Controller\Adminhtml\Language;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;

abstract class ControllerTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $expected
     */
    protected function context(array $parameters, array $expected, ?int $status = null): Context
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $parameters[$key] ?? $default
        );
        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setData')->with($expected)->willReturnSelf();
        if ($status === null) {
            $result->expects(self::never())->method('setHttpResponseCode');
        } else {
            $result->expects(self::once())->method('setHttpResponseCode')->with($status)->willReturnSelf();
        }
        $factory = $this->createMock(ResultFactory::class);
        $factory->expects(self::once())->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($factory);
        return $context;
    }
}
