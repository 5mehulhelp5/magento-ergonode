<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Test\Unit\Controller\Adminhtml\Scan;

use Ergonode\Media\Api\ScanRequesterInterface;
use Ergonode\Media\Api\ScanStatusProviderInterface;
use Ergonode\MediaAdminUi\Controller\Adminhtml\Scan\Start;
use Ergonode\MediaAdminUi\Controller\Adminhtml\Scan\Status;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ScanActionsTest extends TestCase
{
    public function testStartOnlyRequestsBackgroundWorkAndRequiresPost(): void
    {
        $scanner = $this->createMock(ScanRequesterInterface::class);
        $scanner->expects(self::once())->method('request');
        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setData')->with(['success' => true])->willReturnSelf();
        $controller = new Start($this->context($result), $scanner);
        self::assertInstanceOf(HttpPostActionInterface::class, $controller);
        self::assertSame('Ergonode_Media::scan', Start::ADMIN_RESOURCE);
        self::assertSame($result, $controller->execute());
    }

    public function testStartReportsARejectedRequestWithoutSuccess(): void
    {
        $scanner = $this->createStub(ScanRequesterInterface::class);
        $scanner->method('request')->willThrowException(new LocalizedException(__('Busy')));
        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $result->expects(self::once())->method('setData')
            ->with(['success' => false, 'message' => 'Busy'])->willReturnSelf();
        self::assertSame($result, (new Start($this->context($result), $scanner))->execute());
    }

    public function testStatusReadsProgressUnderTheSamePermission(): void
    {
        $status = $this->createStub(ScanStatusProviderInterface::class);
        $status->method('getStatus')->willReturn(['status' => 'pending']);
        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setData')
            ->with(['success' => true, 'scan' => ['status' => 'pending']])->willReturnSelf();
        $controller = new Status($this->context($result), $status);
        self::assertInstanceOf(HttpGetActionInterface::class, $controller);
        self::assertSame(Start::ADMIN_RESOURCE, Status::ADMIN_RESOURCE);
        self::assertSame($result, $controller->execute());
    }

    private function context(Json $result): Context
    {
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getResultFactory')->willReturn($factory);
        return $context;
    }
}
