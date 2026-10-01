<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Block\Adminhtml\Test\Unit\CategoryTree\Mapping;

use Ergonode\Category\Api\CategorySnapshotRemoverInterface;
use Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping\DeleteSnapshot;
use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DeleteSnapshotControllerTest extends TestCase
{
    public function testRemovesSnapshotRowAndReturnsMagentoTreeUnchanged(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['category_tree_id', 0, 7],
            ['ergonode_code', '', 'chairs'],
        ]);
        $remover = $this->createMock(CategorySnapshotRemoverInterface::class);
        $remover->expects(self::once())->method('remove')->with(7, 'chairs');
        $magentoCategory = [
            'id' => 42,
            'parent_id' => 2,
            'label' => 'Chairs',
            'path' => '1/2/42',
            'level' => 2,
            'position' => 7,
            'url_key' => 'chairs-12345678',
        ];
        $provider = $this->createMock(CategoryTreeMappingUiProvider::class);
        $provider->expects(self::once())->method('getConfig')->willReturn([
            'categories' => [],
            'magento_categories' => [$magentoCategory],
        ]);
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with(self::callback(static fn (array $data): bool => $data['success'] === true
                && $data['categories'] === []
                && $data['magento_categories'] === [$magentoCategory]
                && !array_key_exists('removed_category_code', $data)
                && !array_key_exists('sync_metadata', $data)))
            ->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($json);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        $controller = new DeleteSnapshot($context, $remover, $provider, new NullLogger());

        self::assertSame($json, $controller->execute());
    }
}
