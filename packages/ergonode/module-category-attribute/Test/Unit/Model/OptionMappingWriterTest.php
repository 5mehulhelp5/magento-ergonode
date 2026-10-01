<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Test\Unit\Model;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Model\ResourceModel\OptionMappingWriter;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OptionMappingWriterTest extends TestCase
{
    public function testNonOptionAttributePairIsRejectedBeforeStorageAccess(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('option-mappable');

        $this->writer([
            'status' => 'complete',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'magento_attribute_code' => 'meta_title',
        ], [], false)->save(7, [[
            'left' => ['code' => 'source_option'],
            'right' => ['code' => 'option_1'],
        ]], []);
    }

    public function testMagentoOptionFromAnotherAttributeIsRejectedBeforeStorageAccess(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not available for the mapped attribute');

        $this->writer([
            'status' => 'complete',
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            'magento_attribute_code' => 'category_color',
        ], [['code' => 'option_10']], true)->save(7, [[
            'left' => ['code' => 'source_blue'],
            'right' => ['code' => 'option_11'],
        ]], []);
    }

    /**
     * @param array<string, mixed> $attribute
     * @param array<int, array<string, mixed>> $options
     */
    private function writer(array $attribute, array $options, bool $canMapOptions): OptionMappingWriter
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $reader = $this->createStub(MappingReaderInterface::class);
        $reader->method('getAttributeRow')->willReturn($attribute);
        $target = $this->createStub(MagentoOptionProviderInterface::class);
        $target->method('getOptions')->willReturn($options);
        $types = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $types->method('canMapOptions')->willReturn($canMapOptions);

        return new OptionMappingWriter(
            $resource,
            $reader,
            $target,
            $types,
            $this->createStub(MappingVisibilitySaverInterface::class),
            $this->createStub(MappingRowsPersisterInterface::class),
            $this->createStub(LoggerInterface::class)
        );
    }
}
