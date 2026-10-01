<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Test\Unit\Model;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Model\Mapping\MappingPolicy;
use Ergonode\CategoryAttribute\Model\ResourceModel\AttributeMappingWriter;
use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AttributeMappingWriterTest extends TestCase
{
    public function testValidationDoesNotAccessStorageAndAllowsDrafts(): void
    {
        $this->writer()->validate([
            ['left' => ['code' => 'source', 'type' => 'text'], 'right' => null],
            ['left' => null, 'right' => ['code' => 'target', 'type' => 'text']],
        ]);
    }

    public function testDuplicateMappingIsRejectedBeforeStorageAccess(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('mapped more than once');
        $this->writer()->validate([
            ['left' => ['code' => 'source', 'type' => 'text'], 'right' => null],
            ['left' => ['code' => 'source', 'type' => 'text'], 'right' => ['code' => 'target', 'type' => 'text']],
        ]);
    }

    public function testIncompatibleTypesAreRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('types do not match');
        $this->writer()->validate([
            ['left' => ['code' => 'source', 'type' => 'text'], 'right' => ['code' => 'target', 'type' => 'image']],
        ]);
    }

    public function testPendingCreationCannotReachNeutralPersistence(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Resolve Magento category attributes');
        $this->writer()->validate([
            ['left' => ['code' => 'source', 'type' => 'text'],
                'right' => ['code' => 'target', 'type' => 'text', 'pending_create' => true]],
        ]);
    }

    public function testExcludedTargetCannotBeSaved(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not available');
        $this->writer()->validate([['right' => ['code' => 'url_key', 'type' => 'text']]]);
    }

    private function writer(): AttributeMappingWriter
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $types = $this->createStub(AttributeTypeCompatibilityInterface::class);
        $types->method('canMapAttributes')->willReturnCallback(static fn (string $a, string $b): bool => $a === $b);
        $persister = $this->createStub(MappingRowsPersisterInterface::class);
        $persister->method('hash')->willReturn('hash');

        return new AttributeMappingWriter(
            $resource,
            $this->createStub(MappingVisibilitySaverInterface::class),
            $types,
            new MappingPolicy(['url_key']),
            $persister,
            $this->createStub(LoggerInterface::class)
        );
    }
}
