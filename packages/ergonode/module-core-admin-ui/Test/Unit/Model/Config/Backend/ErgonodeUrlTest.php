<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use Ergonode\CoreAdminUi\Model\Config\Backend\ErgonodeUrl;
use Ergonode\CoreAdminUi\Model\Connection\EndpointResolver;

class ErgonodeUrlTest extends TestCase
{
    public function testTrimsAndAcceptsValidUrl(): void
    {
        $model = $this->createModel();
        $model->setValue(' https://example.ergonode.cloud ');

        $model->beforeSave();

        self::assertSame('https://example.ergonode.cloud', $model->getValue());
    }

    public function testRejectsEmptyUrlOnBackend(): void
    {
        $model = $this->createModel();
        $model->setPath('ergonode_connection/test/url');
        $model->setData('groups', ['general' => ['fields' => ['environment' => ['value' => 'test']]]]);
        $model->setValue('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode URL is required.');

        $model->beforeSave();
    }

    public function testAllowsEmptyInactiveEnvironmentUrl(): void
    {
        $model = $this->createModel();
        $model->setPath('ergonode_connection/production/url');
        $model->setData('groups', ['general' => ['fields' => ['environment' => ['value' => 'test']]]]);
        $model->setValue('');
        $model->beforeSave();
        self::assertSame('', $model->getValue());
    }

    public function testSubmittedDisabledStatusAllowsEmptyActiveUrl(): void
    {
        $model = $this->createModel();
        $model->setPath('ergonode_connection/test/url');
        $model->setData('groups', ['general' => ['fields' => [
            'enabled' => ['value' => '0'],
            'environment' => ['value' => 'test'],
        ]]]);
        $model->setValue('');

        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    private function createModel(): ErgonodeUrl
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('1');

        return new ErgonodeUrl(
            $context,
            $this->createStub(Registry::class),
            $config,
            $this->createStub(TypeListInterface::class),
            new EndpointResolver()
        );
    }
}
