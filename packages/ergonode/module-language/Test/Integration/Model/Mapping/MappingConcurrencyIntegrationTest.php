<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Integration\Model\Mapping;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingSaverInterface;
use Ergonode\Language\Exception\MappingConflictException;
use Ergonode\Language\Model\Mapping\MappingCache;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class MappingConcurrencyIntegrationTest extends TestCase
{
    public function testOldEditorCannotUndoNewMappingOrVisibilityChanges(): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->get(ResourceConnection::class);
        $resource->getConnection()->insert(
            $resource->getTableName('ergonode_language'),
            ['language_code' => 'concurrency_test']
        );
        $provider = $manager->get(LanguageMappingStateProviderInterface::class);
        $saver = $manager->get(LanguageStoreMappingSaverInterface::class);
        $before = $provider->getState();
        $mappings = $before->rows;
        $mappings[] = ['language_code' => 'concurrency_test', 'store_id' => null];
        $saved = $saver->save($mappings, [], $before->revision);
        self::assertNotSame($before->revision, $saved['revision']);
        self::assertSame($provider->getState()->revision, $saved['revision']);
        try {
            $saver->save($before->rows, [], $before->revision);
            self::fail('An old editor overwrote newer mappings.');
        } catch (MappingConflictException) {
            self::assertSame($saved['revision'], $provider->getState()->revision);
        }
        $after = $saver->save($provider->getState()->rows, [
            ['source' => 'ergo', 'code' => 'concurrency_test', 'active' => false],
        ], $saved['revision']);
        self::assertNotSame($saved['revision'], $after['revision']);
        self::assertFalse($provider->getState()->languageVisibility['concurrency_test']);
        $manager->get(MappingCache::class)->invalidate();
        $this->expectException(MappingConflictException::class);
        $saver->save($provider->getState()->rows, [], $saved['revision']);
    }
}
