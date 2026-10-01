<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Block\Adminhtml\Test\Unit\CategoryTree;

use Error;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Save;

class SaveControllerTest extends TestCase
{
    private const array CATEGORY_TREE = [
        'category_tree_id' => 7,
        'code' => 'main',
    ];

    public function testHandlesUnexpectedExceptionsWithGenericMessageAndReturnsToMapping(): void
    {
        $exception = new RuntimeException('Database credentials leaked here');
        $categoryTreeRepository = $this->createMock(CategoryTreeRepository::class);
        $categoryTreeRepository->expects(self::once())
            ->method('save')
            ->with(self::CATEGORY_TREE)
            ->willThrowException($exception);
        $messageManager = $this->createMock(ManagerInterface::class);
        $messageManager->expects(self::once())
            ->method('addExceptionMessage')
            ->with(
                $exception,
                self::callback(
                    static fn ($message): bool => (string)$message === 'Could not save the Category Tree.'
                )
            );
        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())
            ->method('setPath')
            ->with('ergonode/category_tree_mapping/edit', ['category_tree_id' => 7])
            ->willReturnSelf();

        $controller = new Save(
            $this->createContext($messageManager, $redirect),
            $categoryTreeRepository
        );

        self::assertSame($redirect, $controller->execute());
    }

    public function testLetsErrorsReachMagentoErrorHandlerWithoutPassingThemAsExceptions(): void
    {
        $error = new Error('Unexpected type mismatch');
        $categoryTreeRepository = $this->createMock(CategoryTreeRepository::class);
        $categoryTreeRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function () use ($error): never {
                throw $error;
            });
        $messageManager = $this->createMock(ManagerInterface::class);
        $messageManager->expects(self::never())->method('addExceptionMessage');
        $redirect = $this->createStub(Redirect::class);

        $controller = new Save(
            $this->createContext($messageManager, $redirect),
            $categoryTreeRepository
        );

        try {
            $controller->execute();
            self::fail('The original Error should reach Magento global error handling.');
        } catch (Error $caughtError) {
            self::assertSame($error, $caughtError);
        }
    }

    public function testReturnsToMappingAfterSavingEmbeddedSettings(): void
    {
        $categoryTree = self::CATEGORY_TREE;
        $categoryTreeRepository = $this->createMock(CategoryTreeRepository::class);
        $categoryTreeRepository->expects(self::once())
            ->method('save')
            ->with($categoryTree)
            ->willReturn(7);
        $messageManager = $this->createStub(ManagerInterface::class);
        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())
            ->method('setPath')
            ->with('ergonode/category_tree_mapping/edit', ['category_tree_id' => 7])
            ->willReturnSelf();
        $controller = new Save(
            $this->createContext($messageManager, $redirect),
            $categoryTreeRepository
        );

        self::assertSame($redirect, $controller->execute());
    }

    private function createContext(ManagerInterface $messageManager, Redirect $redirect): Context
    {
        $request = $this->createStub(Http::class);
        $request->method('getPostValue')->willReturn(self::CATEGORY_TREE);
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($redirect);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getMessageManager')->willReturn($messageManager);
        $context->method('getResultFactory')->willReturn($resultFactory);

        return $context;
    }
}
