<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Integration\Controller\Adminhtml\Connection;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Config\Model\Config;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class ConfigurationTest extends AbstractBackendController
{
    protected $resource = 'Ergonode_Core::config';
    protected $uri = 'backend/admin/system_config/edit/section/ergonode_connection';
    protected $httpMethod = 'GET';
    // Magento redirects configuration sections hidden by their section ACL.
    protected $expectedNoAccessResponseCode = 302;

    public function testMagentoRendersNativeEnvironmentFieldsAndRegisteredModes(): void
    {
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(Http::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getBody());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        foreach (['test', 'production'] as $environment) {
            foreach (['url', 'requests_per_minute'] as $field) {
                $id = 'ergonode_connection_' . $environment . '_' . $field;
                self::assertSame(1, $xpath->query('//*[@id="' . $id . '"]')->length, $id);
            }
            $heading = $xpath->query('//strong[@id="ergonode_connection_' . $environment . '-head"]')->item(0);
            self::assertInstanceOf(DOMElement::class, $heading);
            self::assertStringContainsString('open', $heading->getAttribute('class'));
            $fieldset = $xpath->query('//fieldset[@id="ergonode_connection_' . $environment . '"]')->item(0);
            self::assertSame('div', $fieldset->parentNode->nodeName);
            self::assertSame(0, $xpath->query('.//*[contains(@class, "section-config")]', $fieldset)->length);
            foreach (['consumer', 'publisher'] as $owner) {
                $id = 'ergonode_connection_' . $environment . '_' . $owner . '_api_key';
                $field = $xpath->query('//*[@id="' . $id . '"]');
                self::assertSame(1, $field->length, $id);
                $input = $field->item(0);
                self::assertInstanceOf(DOMElement::class, $input);
                self::assertSame(
                    'groups[' . $environment . '][groups][' . $owner . '][fields][api_key][value]',
                    $input->getAttribute('name')
                );
                $buttonId = 'ergonode_connection_' . $environment . '_' . $owner . '_connection';
                $button = $xpath->query('//*[@id="' . $buttonId . '"]')->item(0);
                self::assertInstanceOf(DOMElement::class, $button);
                $initialization = json_decode($button->getAttribute('data-mage-init'), true, 512, JSON_THROW_ON_ERROR);
                $options = $initialization['Ergonode_CoreAdminUi/js/test-connection'];
                self::assertSame($environment, $options['environment']);
                self::assertSame($owner === 'consumer' ? 'read' : 'write', $options['mode']);
                self::assertSame('ergonode_connection_' . $environment . '_url', $options['urlFieldId']);
                self::assertSame($id, $options['apiKeyFieldId']);
            }
        }
        self::assertSame(1, $xpath->query('//select[@id="ergonode_connection_general_enabled"]')->length);
        $options = $xpath->query('//select[@id="ergonode_connection_general_mode"]/option');
        $values = [];
        foreach ($options as $option) {
            self::assertInstanceOf(DOMElement::class, $option);
            $values[] = $option->getAttribute('value');
        }
        self::assertSame(['read', 'write'], $values);
        self::assertSame(0, $xpath->query('//a[@id="ergonode_connection_test-head"]')->length);
    }

    public function testSaveUsesNativeNestedPathsAndPreservesOtherModeCredentials(): void
    {
        $groups = $this->configurationGroups();
        $this->_objectManager->create(Config::class)->setSection('ergonode_connection')->setGroups($groups)->save();
        $scope = $this->_objectManager->get(ReinitableConfigInterface::class);
        $scope->reinit();
        self::assertSame('read', $scope->getValue('ergonode_connection/general/mode'));
        self::assertSame('test', $scope->getValue('ergonode_connection/general/environment'));
        self::assertSame('7', $scope->getValue('ergonode_connection/test/requests_per_minute'));
        $encrypted = $scope->getValue('ergonode_connection/test/consumer/api_key');
        self::assertNotSame('fixture-read-key', $encrypted);
        self::assertSame(
            'fixture-read-key',
            $this->_objectManager->get(EncryptorInterface::class)->decrypt($encrypted)
        );
        $groups['general']['fields']['mode']['value'] = 'write';
        unset($groups['test']['groups']['consumer']);
        $groups['test']['groups']['publisher'] = ['fields' => ['api_key' => ['value' => 'fixture-write-key']]];
        $this->_objectManager->create(Config::class)->setSection('ergonode_connection')->setGroups($groups)->save();
        $scope->reinit();
        self::assertSame($encrypted, $scope->getValue('ergonode_connection/test/consumer/api_key'));
        $encryptedWrite = $scope->getValue('ergonode_connection/test/publisher/api_key');
        self::assertSame(
            'fixture-write-key',
            $this->_objectManager->get(EncryptorInterface::class)->decrypt($encryptedWrite)
        );
    }

    public function testEmptyActiveCredentialIsRejectedByMagentoSave(): void
    {
        $groups = $this->configurationGroups();
        $groups['test']['groups']['consumer']['fields']['api_key']['value'] = '';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('API key is required');
        $this->_objectManager->create(Config::class)->setSection('ergonode_connection')->setGroups($groups)->save();
    }

    public function testDisabledConnectionCanBeSavedWithoutUrlOrCredential(): void
    {
        $groups = $this->configurationGroups();
        $groups['general']['fields']['enabled']['value'] = '0';
        $groups['test']['fields']['url']['value'] = '';
        $groups['test']['groups']['consumer']['fields']['api_key']['value'] = '';
        $this->_objectManager->create(Config::class)->setSection('ergonode_connection')->setGroups($groups)->save();
        $scope = $this->_objectManager->get(ReinitableConfigInterface::class);
        $scope->reinit();
        self::assertFalse($scope->isSetFlag('ergonode_connection/general/enabled'));
        $connection = $this->_objectManager->get(ConfigProvider::class);
        self::assertFalse($connection->isEnabled());
        self::assertSame('', $connection->getGraphQlUrl());
    }

    /** Brak nadpisania w bazie daje wyłączone połączenie i domyślne środowisko Test. */
    public function testDefaultConnectionIsDisabledAndUsesTestEnvironment(): void
    {
        $resource = $this->_objectManager->get(ResourceConnection::class);
        $resource->getConnection()->delete($resource->getTableName('core_config_data'), [
            'scope = ?' => 'default',
            'scope_id = ?' => 0,
            'path IN (?)' => [
                'ergonode_connection/general/enabled',
                'ergonode_connection/general/environment',
                'ergonode_connection/general/mode',
            ],
        ]);
        $scope = $this->_objectManager->get(ReinitableConfigInterface::class);
        $scope->reinit();
        self::assertFalse($scope->isSetFlag('ergonode_connection/general/enabled'));
        self::assertSame('test', $scope->getValue('ergonode_connection/general/environment'));
        self::assertSame('read', $scope->getValue('ergonode_connection/general/mode'));
    }

    /** Backend odrzuca błędne dane także wtedy, gdy żądanie pomija walidację JavaScript. */
    #[DataProvider('invalidConnectionFields')]
    public function testInvalidActiveProfileIsRejected(string $field, string $value): void
    {
        $groups = $this->configurationGroups();
        $groups['test']['fields'][$field]['value'] = $value;
        $this->expectException(LocalizedException::class);
        $this->_objectManager->create(Config::class)->setSection('ergonode_connection')->setGroups($groups)->save();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidConnectionFields(): array
    {
        return [
            'pusty URL aktywnego profilu' => ['url', ''],
            'niepoprawny URL' => ['url', 'not-a-url'],
            'ujemny limit' => ['requests_per_minute', '-1'],
            'ułamkowy limit' => ['requests_per_minute', '1.5'],
        ];
    }

    /** Zmiana aktywnego środowiska nie nadpisuje URL, limitu ani szyfrogramu klucza drugiego profilu. */
    public function testSwitchingEnvironmentPreservesInactiveProfile(): void
    {
        $groups = $this->configurationGroups();
        $config = $this->_objectManager->create(Config::class);
        $config->setSection('ergonode_connection')->setGroups($groups)->save();
        $scope = $this->_objectManager->get(ReinitableConfigInterface::class);
        $scope->reinit();
        $originalKey = $scope->getValue('ergonode_connection/test/consumer/api_key');
        $production = [
            'general' => ['fields' => ['environment' => ['value' => 'production']]],
            'production' => [
                'fields' => [
                    'url' => ['value' => 'https://production-fixture.ergonode.cloud'],
                    'requests_per_minute' => ['value' => '0'],
                ],
                'groups' => ['consumer' => ['fields' => ['api_key' => ['value' => 'fixture-production-key']]]],
            ],
        ];
        $this->_objectManager->create(Config::class)->setSection('ergonode_connection')->setGroups($production)->save();
        $scope->reinit();
        self::assertSame('production', $scope->getValue('ergonode_connection/general/environment'));
        self::assertSame('https://test.ergonode.cloud', $scope->getValue('ergonode_connection/test/url'));
        self::assertSame('7', $scope->getValue('ergonode_connection/test/requests_per_minute'));
        self::assertSame($originalKey, $scope->getValue('ergonode_connection/test/consumer/api_key'));
        self::assertSame('fixture-production-key', $this->_objectManager->get(EncryptorInterface::class)->decrypt(
            $scope->getValue('ergonode_connection/production/consumer/api_key')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function configurationGroups(): array
    {
        return [
            'general' => ['fields' => [
                'enabled' => ['value' => '1'],
                'environment' => ['value' => 'test'],
                'mode' => ['value' => 'read'],
            ]],
            'test' => [
                'fields' => [
                    'url' => ['value' => 'https://test.ergonode.cloud'],
                    'requests_per_minute' => ['value' => '7'],
                ],
                'groups' => ['consumer' => ['fields' => ['api_key' => ['value' => 'fixture-read-key']]]],
            ],
            'production' => ['fields' => ['url' => ['value' => ''], 'requests_per_minute' => ['value' => '11']]],
        ];
    }
}
