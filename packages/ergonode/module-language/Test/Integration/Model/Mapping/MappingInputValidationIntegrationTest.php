<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Integration\Model\Mapping;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingSaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class MappingInputValidationIntegrationTest extends TestCase
{
    #[DataProvider('invalidInputs')]
    public function testInvalidInputPreservesMappingsVisibilityAndRevision(bool $invalidMapping): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert($resource->getTableName('ergonode_language'), ['language_code' => 'input_validation']);
        $connection->insert($resource->getTableName('ergonode_language_store_mapping'), [
            'language_code' => 'input_validation', 'store_id' => null,
        ]);
        $provider = $manager->get(LanguageMappingStateProviderInterface::class);
        $before = $provider->getState();
        $visibilityTable = $resource->getTableName('ergonode_mapping_visibility');
        $visibility = $connection->fetchAll($connection->select()->from($visibilityTable)->order('entity_id'));
        try {
            $manager->get(LanguageStoreMappingSaverInterface::class)->save(
                $invalidMapping ? [...$before->rows, null] : [],
                [['source' => 'magento', 'code' => '0', 'active' => false]],
                $before->revision
            );
            self::fail('Invalid input was saved.');
        } catch (LocalizedException $exception) {
            self::assertSame($invalidMapping ? 'Invalid language mapping row.'
                : 'Magento Default Values cannot be excluded from language mapping.', $exception->getMessage());
        }
        self::assertSame($before->rows, $provider->getState()->rows);
        self::assertSame($before->revision, $provider->getState()->revision);
        self::assertTrue($provider->getState()->storeVisibility['0']);
        self::assertSame($visibility, $connection->fetchAll(
            $connection->select()->from($visibilityTable)->order('entity_id')
        ));
    }

    /** @return array<string, array{bool}> */
    public static function invalidInputs(): array
    {
        return ['malformed row after valid rows' => [true], 'exclude default values' => [false]];
    }
}
