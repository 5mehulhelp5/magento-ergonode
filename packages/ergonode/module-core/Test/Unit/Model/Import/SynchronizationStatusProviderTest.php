<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Import;

use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Import\SynchronizationStatusProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SynchronizationStatusProviderTest extends TestCase
{
    public function testReturnsOnlyRegisteredSynchronizationsWithTheirCursorState(): void
    {
        $storage = $this->createMock(CursorStorage::class);
        $storage->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                [
                    'attributeStream',
                    [
                        'cursor' => 'attribute-cursor',
                        'synced_at' => '2026-09-04 10:00:00',
                    ],
                ],
                ['category_tree_stream', null],
            ]);

        $provider = new SynchronizationStatusProvider($storage, [
            'category_tree_stream' => [
                'label' => 'Category trees',
                'description' => 'Synchronizes category trees.',
                'sort_order' => 20,
            ],
            'attributeStream' => [
                'label' => 'Attributes',
                'description' => 'Synchronizes attributes.',
                'sort_order' => 10,
            ],
        ]);

        self::assertSame([
            [
                'process_code' => 'attributeStream',
                'label' => 'Attributes',
                'description' => 'Synchronizes attributes.',
                'cursor' => 'attribute-cursor',
                'synced_at' => '2026-09-04 10:00:00',
            ],
            [
                'process_code' => 'category_tree_stream',
                'label' => 'Category trees',
                'description' => 'Synchronizes category trees.',
                'cursor' => null,
                'synced_at' => null,
            ],
        ], $provider->getList());
    }

    public function testRejectsInvalidSynchronizationDefinition(): void
    {
        $storage = $this->createStub(CursorStorage::class);
        $provider = new SynchronizationStatusProvider($storage, [
            'attributeStream' => ['label' => 'Attributes'],
        ]);

        $this->expectException(InvalidArgumentException::class);

        $provider->getList();
    }
}
