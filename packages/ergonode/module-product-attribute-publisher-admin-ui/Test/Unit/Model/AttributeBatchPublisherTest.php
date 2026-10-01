<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Model\Batch;

use Ergonode\ProductAttributePublisher\Api\AttributeDefinitionPublisherInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\ProductAttributePublisherAdminUi\Model\Batch\AttributeBatchPublisher;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeAttributeCreator;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeBatchPublisherTest extends TestCase
{
    public function testPublishesEveryItemAndKeepsIndividualFailuresInTheResult(): void
    {
        $colorState = $this->createStub(AttributeStateInterface::class);
        $colorState->method('getCode')->willReturn('color');
        $brokenState = $this->createStub(AttributeStateInterface::class);
        $brokenState->method('getCode')->willReturn('broken');
        $creator = $this->createMock(ErgonodeAttributeCreator::class);
        $creator->method('prepareMapping')->willReturnCallback(
            static fn (array $source): array => [
                'code' => $source['code'],
                'label' => $source['label'],
                'type' => $source['target_type'],
                'scope' => 'global',
                'pending_create' => true,
            ]
        );
        $creator->expects(self::exactly(2))->method('prepareState')->willReturnMap(
            [
            [['code' => 'color', 'label' => 'Color', 'type' => 'select', 'scope' => '',
                'target_type' => 'select'], $colorState],
            [['code' => 'broken', 'label' => 'Broken', 'type' => 'text', 'scope' => '',
                'target_type' => 'text'], $brokenState],
            ]
        );
        $creator->expects(self::once())->method('verifyPublishedState')->with($colorState);
        $colorResult = $this->createStub(AttributeSynchronizationResultInterface::class);
        $colorResult->method('isSuccessful')->willReturn(true);
        $colorResult->method('getStatus')->willReturn(AttributeSynchronizationResultInterface::STATUS_NOOP);
        $colorResult->method('getMessage')->willReturn('Attribute already exists in Ergonode.');
        $brokenResult = $this->createStub(AttributeSynchronizationResultInterface::class);
        $brokenResult->method('isSuccessful')->willReturn(false);
        $brokenResult->method('getMessage')->willReturn('Remote validation failed.');
        $batchSynchronizer = $this->createMock(AttributeDefinitionPublisherInterface::class);
        $batchSynchronizer->expects(self::once())->method('publishBatch')
            ->with([$colorState, $brokenState])
            ->willReturn(['color' => $colorResult, 'broken' => $brokenResult]);
        $rateLimitGuard = $this->createMock(SynchronizationRateLimitGuard::class);
        $rateLimitGuard->expects(self::exactly(2))->method('throwIfLimited');

        $results = (new AttributeBatchPublisher($creator, $batchSynchronizer, $rateLimitGuard))->publish(
            [
            ['code' => 'color', 'label' => 'Color', 'type' => 'select', 'target_type' => 'select'],
            ['code' => 'broken', 'label' => 'Broken', 'type' => 'text', 'target_type' => 'text'],
            ]
        );

        self::assertSame('existing', $results[0]['status']);
        self::assertSame('Attribute already exists in Ergonode.', $results[0]['message']);
        self::assertFalse($results[0]['mapping']['pending_create']);
        self::assertSame('failed', $results[1]['status']);
        self::assertSame('Remote validation failed.', $results[1]['message']);
    }

    public function testRejectsPayloadAboveTheConfiguredBatchLimit(): void
    {
        $this->expectException(LocalizedException::class);

        (new AttributeBatchPublisher(
            $this->createStub(ErgonodeAttributeCreator::class),
            $this->createStub(AttributeDefinitionPublisherInterface::class),
            $this->createStub(SynchronizationRateLimitGuard::class),
            1
        ))->publish(
            [
            ['code' => 'first'],
            ['code' => 'second'],
            ]
        );
    }

    public function testRejectsBothDuplicateCodesAndPublishesTheIndependentItem(): void
    {
        $colorState = $this->createStub(AttributeStateInterface::class);
        $colorState->method('getCode')->willReturn('color');
        $sizeState = $this->createStub(AttributeStateInterface::class);
        $sizeState->method('getCode')->willReturn('size');
        $creator = $this->createMock(ErgonodeAttributeCreator::class);
        $creator->method('prepareMapping')->willReturnCallback(static fn (array $source): array => [
            'code' => $source['code'], 'pending_create' => true,
        ]);
        $creator->method('prepareState')->willReturnCallback(
            static fn (array $source): AttributeStateInterface => $source['code'] === 'size'
                ? $sizeState : $colorState
        );
        $creator->expects(self::once())->method('verifyPublishedState')->with($sizeState);

        $success = $this->createStub(AttributeSynchronizationResultInterface::class);
        $success->method('isSuccessful')->willReturn(true);
        $success->method('getStatus')->willReturn(AttributeSynchronizationResultInterface::STATUS_SUCCESS);
        $publisher = $this->createMock(AttributeDefinitionPublisherInterface::class);
        $publisher->expects(self::once())->method('publishBatch')
            ->willReturnCallback(static function (array $states) use ($sizeState, $success): array {
                $codes = array_map(static fn (AttributeStateInterface $state): string => $state->getCode(), $states);
                if (count($codes) !== count(array_unique($codes))) {
                    throw new LocalizedException(__('Duplicate attribute code in batch.'));
                }
                self::assertSame([$sizeState], $states);

                return ['size' => $success];
            });
        $rateLimitGuard = $this->createMock(SynchronizationRateLimitGuard::class);
        $rateLimitGuard->expects(self::once())->method('throwIfLimited')->with($success);

        $results = (new AttributeBatchPublisher($creator, $publisher, $rateLimitGuard))->publish([
            ['code' => ' color ', 'label' => 'Color', 'target_type' => 'text'],
            ['code' => 'color', 'label' => 'Colour', 'target_type' => 'text'],
            ['code' => 'size', 'label' => 'Size', 'target_type' => 'numeric'],
        ]);

        self::assertCount(3, $results);
        self::assertSame('failed', $results[0]['status']);
        self::assertSame('failed', $results[1]['status']);
        self::assertSame($results[0]['message'], $results[1]['message']);
        self::assertStringContainsString('more than once', $results[0]['message']);
        self::assertSame('synchronized', $results[2]['status']);
        self::assertFalse($results[2]['mapping']['pending_create']);
    }

    public function testInvalidDuplicateCannotMakeTheOtherCopyLookSuccessful(): void
    {
        $colorState = $this->createStub(AttributeStateInterface::class);
        $colorState->method('getCode')->willReturn('color');
        $creator = $this->createStub(ErgonodeAttributeCreator::class);
        $creator->method('prepareMapping')->willReturn(['code' => 'color', 'pending_create' => true]);
        $creator->method('prepareState')->willReturn($colorState);
        $success = $this->createStub(AttributeSynchronizationResultInterface::class);
        $success->method('isSuccessful')->willReturn(true);
        $success->method('getStatus')->willReturn(AttributeSynchronizationResultInterface::STATUS_SUCCESS);
        $publisher = $this->createStub(AttributeDefinitionPublisherInterface::class);
        $publisher->method('publishBatch')->willReturn(['color' => $success]);

        $results = (new AttributeBatchPublisher(
            $creator,
            $publisher,
            $this->createStub(SynchronizationRateLimitGuard::class)
        ))->publish([
            ['code' => 'color', 'label' => 'Color', 'target_type' => 'text'],
            ['code' => 'color', 'label' => 'Colour', 'target_type' => ''],
        ]);

        self::assertSame('failed', $results[0]['status']);
        self::assertSame('failed', $results[1]['status']);
        self::assertSame($results[0]['message'], $results[1]['message']);
    }
}
