<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Integration\Controller\Adminhtml\Language;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingSaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\Attributes\DataProvider;

#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class SaveTest extends MutationControllerTestCase
{
    protected const string SERVICE = LanguageStoreMappingSaverInterface::class;
    protected const string OPERATION = 'save';

    protected $resource = 'Ergonode_Language::language_mapping_save';
    protected $uri = 'backend/ergonode/language/save';

    public function testStaleEditorReceives409AndCannotOverwriteNewerDatabaseState(): void
    {
        $resource = $this->_objectManager->get(ResourceConnection::class);
        $resource->getConnection()->insert(
            $resource->getTableName('ergonode_language'),
            ['language_code' => 'controller_conflict']
        );
        $provider = $this->_objectManager->get(LanguageMappingStateProviderInterface::class);
        $before = $provider->getState();
        $mappings = $before->rows;
        $mappings[] = ['language_code' => 'controller_conflict', 'store_id' => null];
        $this->_objectManager->get(LanguageStoreMappingSaverInterface::class)->save($mappings, [
            ['source' => 'ergo', 'code' => 'controller_conflict', 'active' => false],
        ], $before->revision);
        $newer = $provider->getState();
        self::assertNotSame($before->revision, $newer->revision);

        $this->getRequest()->setParam('payload', json_encode([
            'mappings' => $before->rows, 'revision' => $before->revision,
            'visibility' => [['source' => 'ergo', 'code' => 'controller_conflict', 'active' => true]],
        ], JSON_THROW_ON_ERROR));
        $this->dispatch($this->uri);

        self::assertSame(409, $this->getResponse()->getHttpResponseCode());
        self::assertFalse($this->responseData()['success']);
        self::assertArrayNotHasKey('revision', $this->responseData());
        self::assertSame($newer->rows, $provider->getState()->rows);
        self::assertSame($newer->revision, $provider->getState()->revision);
        self::assertFalse($provider->getState()->languageVisibility['controller_conflict']);
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidHttpPayloadNeverReachesSaver(string $payload): void
    {
        $this->replaceService()->expects(self::never())->method('save');
        $this->getRequest()->setParam('payload', $payload);
        $this->dispatch($this->uri);
        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertFalse($this->responseData()['success']);
        self::assertArrayNotHasKey('revision', $this->responseData());
    }

    /** @return array<string, array{string}> */
    public static function invalidRequests(): array
    {
        return [
            'missing payload' => [''], 'malformed JSON' => ['{broken-json'],
            'invalid mappings' => ['{"mappings":false}'], 'missing revision' => ['{"mappings":[]}'],
        ];
    }
}
