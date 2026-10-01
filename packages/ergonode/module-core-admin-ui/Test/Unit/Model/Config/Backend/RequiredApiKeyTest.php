<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use Ergonode\CoreAdminUi\Model\Config\Backend\RequiredApiKey;

class RequiredApiKeyTest extends TestCase
{
    public function testRejectsEmptyApiKeyForActiveProfile(): void
    {
        $model = $this->createModel();
        $model->setPath('ergonode_connection/test/example/api_key');
        $model->setData('groups', ['general' => ['fields' => [
            'environment' => ['value' => 'test'],
            'mode' => ['value' => 'example'],
        ]]]);
        $groups = $model->getData('groups');
        $groups['general']['fields']['enabled']['value'] = '1';
        $model->setData('groups', $groups);
        $model->setValue('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('API key is required for the active environment and operating mode.');

        $model->beforeSave();
    }

    public function testAllowsEmptyApiKeyForInactiveProfile(): void
    {
        $model = $this->createModel();
        $model->setPath('ergonode_connection/test/example/api_key');
        $model->setData('groups', ['general' => ['fields' => [
            'environment' => ['value' => 'production'],
            'mode' => ['value' => 'example'],
        ]]]);
        $groups = $model->getData('groups');
        $groups['general']['fields']['enabled']['value'] = '1';
        $model->setData('groups', $groups);
        $model->setValue('');

        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    public function testEncryptsSubmittedApiKeyForActiveProfile(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects(self::once())->method('encrypt')->with('new-key')->willReturn('encrypted-key');
        $model = $this->createModel(null, $encryptor);
        $model->setPath('ergonode_connection/test/example/api_key');
        $model->setData('groups', ['general' => ['fields' => [
            'environment' => ['value' => 'test'],
            'mode' => ['value' => 'example'],
        ]]]);
        $groups = $model->getData('groups');
        $groups['general']['fields']['enabled']['value'] = '1';
        $model->setData('groups', $groups);
        $model->setValue('new-key');

        $model->beforeSave();

        self::assertSame('encrypted-key', $model->getValue());
    }

    public function testAcceptsMaskedApiKeyOnlyWhenSavedSecretExists(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('encrypted-old-key');
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects(self::once())->method('decrypt')
            ->with('encrypted-old-key')
            ->willReturn('saved-key');
        $model = $this->createModel($config, $encryptor);
        $model->setPath('ergonode_connection/test/example/api_key');
        $model->setData('groups', ['general' => ['fields' => [
            'environment' => ['value' => 'test'],
            'mode' => ['value' => 'example'],
        ]]]);
        $groups = $model->getData('groups');
        $groups['general']['fields']['enabled']['value'] = '1';
        $model->setData('groups', $groups);
        $model->setValue('******');

        $model->beforeSave();

        self::assertSame('******', $model->getValue());
    }

    public function testDisabledConnectionAllowsEmptyActiveKey(): void
    {
        $model = $this->createModel();
        $model->setPath('ergonode_connection/test/example/api_key');
        $model->setData('groups', ['general' => ['fields' => [
            'enabled' => ['value' => '0'],
            'environment' => ['value' => 'test'],
            'mode' => ['value' => 'example'],
        ]]]);
        $model->setValue('');

        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    private function createModel(
        ?ScopeConfigInterface $config = null,
        ?EncryptorInterface $encryptor = null
    ): RequiredApiKey {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return new class(
            $context,
            $this->createStub(Registry::class),
            $config ?? $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class),
            $encryptor ?? $this->createStub(EncryptorInterface::class)
        ) extends RequiredApiKey {
            protected function getModeCode(): string
            {
                return 'example';
            }
        };
    }
}
