<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\AttributeConsumer\Api\OptionSynchronizationParticipantInterface;
use Ergonode\AttributeConsumer\Model\Sync\OptionSynchronizationPool;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

class OptionSynchronizationPoolTest extends TestCase
{
    public function testDelegatesNormalizedCodesAndCombinesParticipantResults(): void
    {
        $product = $this->createMock(OptionSynchronizationParticipantInterface::class);
        $product->expects(self::once())
            ->method('executeForAttributeCodes')
            ->with(['color', 'size'])
            ->willReturn([
                'mappings' => [['mapping_id' => 10]],
                'summary' => ['created' => 1, 'errors' => 0],
            ]);
        $category = $this->createMock(OptionSynchronizationParticipantInterface::class);
        $category->expects(self::once())
            ->method('executeForAttributeCodes')
            ->with(['color', 'size'])
            ->willReturn([
                'mappings' => [['mapping_id' => 20]],
                'summary' => ['created' => 2, 'errors' => 1],
            ]);

        $result = (new OptionSynchronizationPool([
            'product' => $product,
            'category' => $category,
        ]))->executeForAttributeCodes([' color ', 'size', 'color', '']);

        self::assertSame([['mapping_id' => 10], ['mapping_id' => 20]], $result['mappings']);
        self::assertSame(3, $result['summary']['created']);
        self::assertSame(1, $result['summary']['errors']);
    }

    public function testRejectsParticipantThatDoesNotImplementContract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OptionSynchronizationPool(['invalid' => new stdClass()]);
    }
}
