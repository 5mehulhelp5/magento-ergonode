<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Block\Adminhtml\Test\Unit\CategoryTree;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationRunInterface;
use Ergonode\CategoryConsumerAdminUi\Model\CategorySynchronizationResponse;
use Ergonode\CategoryConsumerAdminUi\Controller\Adminhtml\Category\Tree\Sync;
use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Magento\Backend\Model\Session;

class SyncControllerTest extends TestCase
{
    /**
     * @param array{events: int, trees: int, conflicts: int, cursor: string|null} $stats
     */
    #[DataProvider('statsProvider')]
    public function testSynchronizesThroughSharedProcessAndReturnsUpdatedConfig(
        array $stats,
        bool $expectedSuccess,
        string $expectedMessage
    ): void {
        $process = $this->createMock(CategorySynchronizationRunInterface::class);
        $process->expects(self::once())->method('execute')
            ->with(str_repeat('a', 32), 'all', 'sync', false)->willReturn([
            'state' => $expectedSuccess ? 'success' : 'warning', 'stats' => $stats, 'message' => $expectedMessage,
        ]);
        $config = [
            'category_tree_id' => 7,
            'categories' => [['code' => 'chairs']],
            'magento_categories' => [['id' => 12]],
        ];
        $provider = $this->createMock(CategoryTreeMappingUiProvider::class);
        $provider->expects(self::once())->method('getConfig')->with(true)->willReturn($config);
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with(self::callback(static fn (array $data): bool => $data['success'] === $expectedSuccess
                && $data['completed'] === true
                && $data['stats'] === $stats
                && $data['config'] === $config
                && (string)$data['message'] === $expectedMessage))
            ->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($json);
        $context = $this->createStub(Context::class);
        $context->method('getResultFactory')->willReturn($resultFactory);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key): mixed => ['run_id' => str_repeat('a', 32),
                'synchronization_scope' => 'all', 'synchronization_action' => 'sync', 'resume' => false][$key]
        );
        $context->method('getRequest')->willReturn($request);
        $context->method('getSession')->willReturn($this->createStub(Session::class));

        $controller = new Sync(
            $context,
            $process,
            new CategorySynchronizationResponse($provider)
        );

        self::assertSame($json, $controller->execute());
    }

    public function testResetAndSyncRunsTheSynchronizationProcessFromTheBeginning(): void
    {
        $stats = ['events' => 0, 'trees' => 0, 'conflicts' => 0, 'cursor' => null];
        $process = $this->createMock(CategorySynchronizationRunInterface::class);
        $process->expects(self::once())->method('execute')
            ->with(str_repeat('a', 32), 'data', 'reset-cursor-and-sync', false)->willReturn([
            'state' => 'success', 'stats' => $stats, 'message' => 'Everything is up to date.',
        ]);
        $provider = $this->createStub(CategoryTreeMappingUiProvider::class);
        $provider->method('getConfig')->willReturn([]);
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($json);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key): mixed => ['run_id' => str_repeat('a', 32),
                'synchronization_scope' => 'data',
                'synchronization_action' => 'reset-cursor-and-sync', 'resume' => false][$key]
        );
        $context = $this->createStub(Context::class);
        $context->method('getResultFactory')->willReturn($resultFactory);
        $context->method('getRequest')->willReturn($request);
        $context->method('getSession')->willReturn($this->createStub(Session::class));

        $controller = new Sync(
            $context,
            $process,
            new CategorySynchronizationResponse($provider)
        );

        self::assertSame($json, $controller->execute());
    }

    /**
     * @return array<string, array{
     *     array{events: int, trees: int, conflicts: int, cursor: string|null},
     *     bool,
     *     string
     * }>
     */
    public static function statsProvider(): array
    {
        return [
            'success' => [[
                'events' => 2,
                'trees' => 1,
                'conflicts' => 0,
                'cursor' => 'cursor-2',
            ], true, 'Changes applied.'],
            'no changes' => [[
                'events' => 0,
                'trees' => 0,
                'conflicts' => 0,
                'cursor' => 'cursor-2',
            ], true, 'Everything is up to date.'],
            'completed with conflicts' => [[
                'events' => 2,
                'trees' => 1,
                'conflicts' => 3,
                'cursor' => 'cursor-2',
            ], false, 'Completed with 3 conflict(s).'],
        ];
    }
}
