<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Test\Unit\Model\Batch;

use Ergonode\CategoryAttributePublisherAdminUi\Model\Batch\CategoryAttributeBatchPublisher;
use Ergonode\CategoryAttributePublisherAdminUi\Model\ErgonodeCategoryAttributeCreator;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use PHPUnit\Framework\TestCase;

class CategoryAttributeBatchPublisherTest extends TestCase
{
    public function testCollectsCreatorRejectionWithoutStoppingBatch(): void
    {
        $state = $this->createStub(AttributeStateInterface::class);
        $state->method('getScope')->willReturn('LOCAL');
        $creator = $this->createMock(ErgonodeCategoryAttributeCreator::class);
        $creator->expects(self::exactly(2))->method('synchronizeFromMagento')
            ->willReturnCallback(static function (string $code, string $type) use ($state): AttributeStateInterface {
                if ($code === 'path') {
                    throw new LocalizedException(__(
                        'Magento category attribute "%1" is not available for Ergonode mapping.',
                        $code
                    ));
                }
                self::assertSame('display_mode', $code);
                self::assertSame('select', $type);
                return $state;
            });

        $results = (new CategoryAttributeBatchPublisher($creator))->publish([
            ['code' => 'display_mode', 'label' => 'Display Mode', 'target_type' => 'select'],
            ['code' => 'path', 'label' => 'Path', 'target_type' => 'text'],
        ]);

        self::assertSame('synchronized', $results[0]['status']);
        self::assertSame('failed', $results[1]['status']);
        self::assertStringContainsString('not available', $results[1]['message']);
    }

    public function testUsesPublishedScopeRatherThanClientScope(): void
    {
        foreach (['LOCAL' => 'local', 'GLOBAL' => 'global'] as $publishedScope => $expected) {
            $state = $this->createStub(AttributeStateInterface::class);
            $state->method('getScope')->willReturn($publishedScope);
            $creator = $this->createStub(ErgonodeCategoryAttributeCreator::class);
            $creator->method('synchronizeFromMagento')->willReturn($state);
            $results = (new CategoryAttributeBatchPublisher($creator))->publish([
                ['code' => 'score', 'target_type' => 'numeric', 'scope' => 'untrusted'],
            ]);
            self::assertSame($expected, $results[0]['mapping']['scope']);
            self::assertFalse($results[0]['mapping']['pending_create']);
        }
    }

    public function testRateLimitStopsBatchBeforeTheNextAttribute(): void
    {
        $exception = new GraphQlRequestException('Wait', GraphQlRequestException::FAILURE_RATE_LIMIT, 429, 30);
        $creator = $this->createMock(ErgonodeCategoryAttributeCreator::class);
        $creator->expects(self::once())->method('synchronizeFromMagento')
            ->with('first', 'text')->willThrowException($exception);
        $this->expectExceptionObject($exception);
        (new CategoryAttributeBatchPublisher($creator))->publish([
            ['code' => 'first', 'target_type' => 'text'],
            ['code' => 'second', 'target_type' => 'text'],
        ]);
    }
}
