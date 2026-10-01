<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Block\Adminhtml\Test\Unit\CategoryTree;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationRunInterface;
use Ergonode\CategoryConsumerAdminUi\Controller\Adminhtml\Category\Tree\PauseSync;
use Ergonode\CategoryConsumerAdminUi\Controller\Adminhtml\Category\Tree\Sync;
use Ergonode\CategoryConsumerAdminUi\Model\CategorySynchronizationResponse;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;

class SyncControlControllerTest extends TestCase
{
    public function testExecutionReleasesTheSessionBeforeStartingWork(): void
    {
        $closed = false;
        $context = $this->context($closed);
        $run = $this->createMock(CategorySynchronizationRunInterface::class);
        $run->expects(self::once())->method('execute')
            ->with(str_repeat('a', 32), 'tree', 'sync', true)
            ->willReturnCallback(static function () use (&$closed): array {
                self::assertTrue($closed);
                return ['state' => 'paused'];
            });
        $response = $this->createStub(CategorySynchronizationResponse::class);
        $response->method('format')->willReturn(['state' => 'paused']);
        (new Sync($context, $run, $response))->execute();
    }

    public function testPauseUsesTheSameAclAndNeverExecutesTheSynchronization(): void
    {
        $closed = false;
        $context = $this->context($closed);
        $run = $this->createMock(CategorySynchronizationRunInterface::class);
        $run->expects(self::never())->method('execute');
        $run->expects(self::once())->method('pause')->with(str_repeat('a', 32))
            ->willReturnCallback(static function () use (&$closed): void {
                self::assertTrue($closed);
            });
        (new PauseSync($context, $run))->execute();
        self::assertSame(Sync::ADMIN_RESOURCE, PauseSync::ADMIN_RESOURCE);
    }

    private function context(bool &$closed): Context
    {
        $context = $this->createStub(Context::class);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key): mixed => [
            'run_id' => str_repeat('a', 32), 'synchronization_scope' => 'tree',
            'synchronization_action' => 'sync', 'resume' => 1,
        ][$key] ?? null);
        $context->method('getRequest')->willReturn($request);
        $session = $this->createMock(Session::class);
        $session->expects(self::once())->method('writeClose')->willReturnCallback(
            static function () use (&$closed, $session): Session {
                $closed = true;
                return $session;
            }
        );
        $context->method('getSession')->willReturn($session);
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnSelf();
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($json);
        $context->method('getResultFactory')->willReturn($factory);

        return $context;
    }
}
