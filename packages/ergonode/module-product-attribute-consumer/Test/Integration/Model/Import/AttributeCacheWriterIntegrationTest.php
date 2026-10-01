<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Import\AttributeNormalizer;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class AttributeCacheWriterIntegrationTest extends TestCase
{
    private const string ATTRIBUTE_CODE = 'attribute_cache_writer_it';
    private const string UNCHANGED_TIMESTAMP = '2000-01-01 00:00:00';

    public function testAttributeWriteIsSkippedUntilNormalizedStateChanges(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $writer = Bootstrap::getObjectManager()->get(AttributeCacheWriter::class);
        $normalizer = Bootstrap::getObjectManager()->get(AttributeNormalizer::class);
        $table = $resource->getTableName('ergonode_attribute');
        $connection = $resource->getConnection();
        $attribute = $normalizer->normalizeAttribute($this->attributeNode('Description'));

        self::assertSame([self::ATTRIBUTE_CODE => 'inserted'], $writer->saveAttributes([$attribute]));
        $connection->update(
            $table,
            ['synced_at' => self::UNCHANGED_TIMESTAMP, 'updated_at' => self::UNCHANGED_TIMESTAMP],
            ['code = ?' => self::ATTRIBUTE_CODE]
        );

        self::assertSame([self::ATTRIBUTE_CODE => 'unchanged'], $writer->saveAttributes([$attribute]));
        self::assertSame(
            self::UNCHANGED_TIMESTAMP,
            $this->timestamp($resource, $table, 'code', self::ATTRIBUTE_CODE)
        );

        $changed = $normalizer->normalizeAttribute($this->attributeNode('Changed description'));
        self::assertSame([self::ATTRIBUTE_CODE => 'updated'], $writer->saveAttributes([$changed]));
        self::assertNotSame(
            self::UNCHANGED_TIMESTAMP,
            $this->timestamp($resource, $table, 'code', self::ATTRIBUTE_CODE)
        );
    }

    public function testOptionWriteIsSkippedUntilNormalizedStateChanges(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $writer = Bootstrap::getObjectManager()->get(AttributeCacheWriter::class);
        $normalizer = Bootstrap::getObjectManager()->get(AttributeNormalizer::class);
        $table = $resource->getTableName('ergonode_attribute_option');
        $connection = $resource->getConnection();
        $option = $normalizer->normalizeOption($this->optionNode(), 1);

        self::assertSame(['red' => 'inserted'], $writer->saveOptions(self::ATTRIBUTE_CODE, [$option]));
        $connection->update(
            $table,
            ['synced_at' => self::UNCHANGED_TIMESTAMP, 'updated_at' => self::UNCHANGED_TIMESTAMP],
            ['attribute_code = ?' => self::ATTRIBUTE_CODE]
        );

        self::assertSame(['red' => 'unchanged'], $writer->saveOptions(self::ATTRIBUTE_CODE, [$option]));
        self::assertSame(
            self::UNCHANGED_TIMESTAMP,
            $this->timestamp($resource, $table, 'attribute_code', self::ATTRIBUTE_CODE)
        );

        $changed = $normalizer->normalizeOption($this->optionNode(), 2);
        self::assertSame(['red' => 'updated'], $writer->saveOptions(self::ATTRIBUTE_CODE, [$changed]));
        self::assertNotSame(
            self::UNCHANGED_TIMESTAMP,
            $this->timestamp($resource, $table, 'attribute_code', self::ATTRIBUTE_CODE)
        );
    }

    /** @return array<string, mixed> */
    private function attributeNode(string $label): array
    {
        return [
            '__typename' => 'TextAttribute',
            'code' => self::ATTRIBUTE_CODE,
            'scope' => 'global',
            'name' => [['language' => 'en_US', 'value' => $label]],
            'unique' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function optionNode(): array
    {
        return [
            'code' => 'red',
            'name' => [['language' => 'en_US', 'value' => 'Red']],
        ];
    }

    private function timestamp(
        ResourceConnection $resource,
        string $table,
        string $keyColumn,
        string $keyValue
    ): string {
        return (string)$resource->getConnection()->fetchOne(
            $resource->getConnection()->select()
                ->from($table, ['updated_at'])
                ->where($keyColumn . ' = ?', $keyValue)
        );
    }
}
