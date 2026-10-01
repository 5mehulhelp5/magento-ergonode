<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Integration\Controller\Adminhtml\Language;

use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

abstract class MutationControllerTestCase extends AbstractBackendController
{
    protected const string SERVICE = '';
    protected const string OPERATION = '';

    protected $httpMethod = 'POST';

    protected function setUp(): void
    {
        parent::setUp();
        $this->getRequest()->setMethod('POST')->setParams([
            'payload' => json_encode(['mappings' => [], 'revision' => str_repeat('a', 64)], JSON_THROW_ON_ERROR),
            'code' => 'controller_fixture',
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
        ]);
        $this->getRequest()->getQuery()->set('isAjax', true);
        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
    }

    public function testAclHasAccess(): void
    {
        $service = $this->replaceService();
        $expectation = $service->expects(self::once())->method(static::OPERATION);
        if (static::OPERATION === 'save') {
            $expectation->with([], [], str_repeat('a', 64))->willReturn([
                'stats' => ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0],
                'revision' => str_repeat('b', 64),
            ]);
        } elseif (static::OPERATION === 'refreshErgonodeLanguageCodes') {
            $expectation->willReturn(['pl_PL']);
        } else {
            $expectation->with('controller_fixture');
        }
        parent::testAclHasAccess();
        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertTrue($this->responseData()['success']);
    }

    public function testAclNoAccess(): void
    {
        $this->replaceService()->expects(self::never())->method(static::OPERATION);
        parent::testAclNoAccess();
    }

    #[DataProvider('invalidFormKeys')]
    public function testInvalidFormKeyNeverReachesDomainService(string $formKey): void
    {
        $this->replaceService()->expects(self::never())->method(static::OPERATION);
        // AbstractController::dispatch adds a valid POST form_key. The explicit
        // request parameter takes precedence, preserving the invalid value under test.
        $this->getRequest()->setParam('form_key', $formKey);
        $this->dispatch($this->uri);
        self::assertTrue($this->responseData()['error']);
        self::assertSame('Invalid Form Key. Please refresh the page.', $this->responseData()['message']);
        self::assertArrayNotHasKey('success', $this->responseData());
    }

    /** @return array<string, array{string}> */
    public static function invalidFormKeys(): array
    {
        return ['empty form key' => [''], 'wrong form key' => ['invalid-form-key']];
    }

    #[DataProvider('invalidMethods')]
    public function testNonPostRequestNeverReachesDomainService(string $method): void
    {
        $this->replaceService()->expects(self::never())->method(static::OPERATION);
        $this->getRequest()->setMethod($method);
        $this->dispatch($this->uri);
        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /** @return array<string, array{string}> */
    public static function invalidMethods(): array
    {
        return ['GET' => ['GET'], 'PUT' => ['PUT']];
    }

    protected function replaceService(): MockObject
    {
        $service = $this->createMock(static::SERVICE);
        $this->_objectManager->addSharedInstance($service, static::SERVICE, true);
        return $service;
    }

    /** @return array<string, mixed> */
    protected function responseData(): array
    {
        return json_decode($this->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function tearDown(): void
    {
        $this->_objectManager->removeSharedInstance(static::SERVICE, true);
        parent::tearDown();
    }
}
