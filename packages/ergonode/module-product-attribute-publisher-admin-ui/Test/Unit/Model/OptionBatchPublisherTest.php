<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisherAdminUi\Test\Unit\Model\Batch;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributePublisher\Api\OptionDefinitionPublisherInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionSynchronizationResult;
use Ergonode\ProductAttributePublisherAdminUi\Model\Batch\OptionBatchPublisher;
use Ergonode\ProductAttributePublisherAdminUi\Model\ErgonodeOptionCreator;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use PHPUnit\Framework\TestCase;

class OptionBatchPublisherTest extends TestCase
{
    public function testUsesOneBatchAndMapsExistingOptionAsWarning(): void
    {
        $provider = $this->createMock(AttributeMappingProvider::class);
        $provider->expects(self::once())->method('getMappingRow')->with(17)->willReturn(
            [
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'magento_color',
            ]
        );
        $creator = $this->createMock(ErgonodeOptionCreator::class);
        $creator->method('prepareMapping')->willReturnCallback(
            static fn (AttributeOptionState $state): array => [
                'code' => $state->getCode(),
                'label' => $state->getNames()['pl_PL'],
                'type' => 'option',
                'scope' => 'pl_PL',
                'pending_create' => false,
            ]
        );
        $creator->method('prepareState')->willReturnCallback(
            static function (string $magento, string $ergonode, array $source): AttributeOptionState {
                self::assertSame('magento_color', $magento);
                self::assertSame('color', $ergonode);

                return new AttributeOptionState(strtolower($source['label']), ['pl_PL' => $source['label']]);
            }
        );
        $creator->expects(self::once())->method('verifyPublishedOptions')->with('color');
        $batchSynchronizer = $this->createMock(OptionDefinitionPublisherInterface::class);
        $batchSynchronizer->expects(self::once())
            ->method('publishBatch')
            ->with(
                'color',
                self::callback(
                    static function (array $states): bool {
                        self::assertSame(
                            ['red', 'blue'],
                            array_map(
                                static fn (AttributeOptionState $state): string => $state->getCode(),
                                $states
                            )
                        );

                        return true;
                    }
                )
            )
            ->willReturn(
                [
                'red' => new AttributeOptionSynchronizationResult(
                    AttributeOptionSynchronizationResultInterface::STATUS_NOOP,
                    AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT,
                    [],
                    'Option already exists in Ergonode and is ready for mapping.'
                ),
                'blue' => new AttributeOptionSynchronizationResult(
                    AttributeOptionSynchronizationResultInterface::STATUS_SUCCESS,
                    AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT
                ),
                ]
            );
        $rateLimitGuard = $this->createMock(SynchronizationRateLimitGuard::class);
        $rateLimitGuard->expects(self::exactly(2))->method('throwIfLimited');

        $results = (new OptionBatchPublisher(
            $provider,
            $creator,
            $batchSynchronizer,
            $rateLimitGuard
        ))->publish(
            17,
            [
            ['code' => 'option_10', 'label' => 'Red'],
            ['code' => 'option_11', 'label' => 'Blue'],
            ]
        );

        self::assertSame(['existing', 'synchronized'], array_column($results, 'status'));
        self::assertStringContainsString('already exists', $results[0]['message']);
        self::assertSame('pl_PL', $results[0]['mapping']['scope']);
    }

    public function testRejectsGeneratedCodeCollisionBeforeRemoteMutation(): void
    {
        $provider = $this->createStub(AttributeMappingProvider::class);
        $provider->method('getMappingRow')->willReturn([
            'ergonode_attribute_code' => 'color', 'magento_attribute_code' => 'magento_color',
        ]);
        $creator = $this->createMock(ErgonodeOptionCreator::class);
        $creator->method('prepareMapping')->willReturn(['code' => 'blue']);
        $creator->method('prepareState')->willReturn(
            new AttributeOptionState('blue', ['pl_PL' => 'Blue'])
        );
        $creator->expects(self::never())->method('verifyPublishedOptions');
        $batchSynchronizer = $this->createMock(OptionDefinitionPublisherInterface::class);
        $batchSynchronizer->expects(self::never())->method('publishBatch');

        $results = (new OptionBatchPublisher(
            $provider,
            $creator,
            $batchSynchronizer,
            $this->createStub(SynchronizationRateLimitGuard::class)
        ))->publish(
            17,
            [
            ['code' => 'option_10', 'label' => 'Blue'],
            ['code' => 'option_11', 'label' => 'Blue!'],
            ]
        );

        self::assertSame(['failed', 'failed'], array_column($results, 'status'));
        self::assertStringContainsString('generated for more than one Magento option', $results[0]['message']);
        self::assertStringContainsString('generated for more than one Magento option', $results[1]['message']);
    }
}
