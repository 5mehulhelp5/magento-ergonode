<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Test\Unit\Controller\Adminhtml\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionManagementInterface;
use Ergonode\PublisherAdminUi\Controller\Adminhtml\Rest\Login;
use Ergonode\PublisherAdminUi\Model\Rest\ConnectionStorage;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoginTest extends TestCase
{
    #[DataProvider('rememberValues')]
    public function testPersistenceRequiresExplicitOptIn(?string $remember, bool $expected): void
    {
        $parameters = ['username' => 'fixture@example.test', 'password' => 'fixture-only'];
        if ($remember !== null) {
            $parameters['remember_me'] = $remember;
        }
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $parameters[$key] ?? $default
        );
        $storage = $this->createMock(ConnectionStorage::class);
        $storage->expects(self::once())->method('selectPersistence')->with($expected);
        $connection = $this->createMock(ConnectionManagementInterface::class);
        $connection->expects(self::once())->method('login')->with('fixture@example.test', 'fixture-only');
        $connection->method('status')->willReturn(['authenticated' => true, 'email' => 'fixture@example.test']);
        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setData')->with([
            'success' => true, 'authenticated' => true, 'email' => 'fixture@example.test',
        ])->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($factory);
        self::assertSame($result, (new Login($context, $connection, $storage))->execute());
    }

    /** @return array<string, array{?string, bool}> */
    public static function rememberValues(): array
    {
        return ['missing' => [null, false], 'unchecked' => ['0', false], 'checked' => ['1', true]];
    }
}
