<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\ProductAttributeConsumer\Api\OptionSynchronizationProcessInterface;
use Ergonode\ProductAttributeConsumer\Model\Sync\ProductOptionSynchronizationParticipant;
use PHPUnit\Framework\TestCase;

class ProductOptionSynchronizationParticipantTest extends TestCase
{
    public function testDelegatesOnlyToProductMappingSynchronization(): void
    {
        $expected = [
            'mappings' => [['mapping_id' => 17]],
            'summary' => ['created' => 1],
        ];
        $process = $this->createMock(OptionSynchronizationProcessInterface::class);
        $process->expects(self::once())
            ->method('executeForAttributeCodes')
            ->with(['color'])
            ->willReturn($expected);

        self::assertSame(
            $expected,
            (new ProductOptionSynchronizationParticipant($process))->executeForAttributeCodes(['color'])
        );
    }
}
