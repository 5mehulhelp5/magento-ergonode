<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisherAdminUi\Test\Unit\Model;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\CategoryAttributePublisherAdminUi\Model\Mapping\SourceMetadata;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingProvider;
use Ergonode\CategoryAttributePublisher\Api\CategoryAttributeRegistrySynchronizerInterface;
use Ergonode\CategoryAttributePublisher\Model\Provider\CategoryAttributeSourceStateBuilder;
use Ergonode\CategoryAttributePublisherAdminUi\Model\ErgonodeCategoryAttributeCreator;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ErgonodeCategoryAttributeCreatorTest extends TestCase
{
    public function testCreatesRegistersAndRefreshesCategoryAttribute(): void
    {
        $calls = [];
        $state = $this->createStub(AttributeStateInterface::class);
        $state->method('getCode')->willReturn('category_banner');
        $builder = $this->createMock(CategoryAttributeSourceStateBuilder::class);
        $builder->expects(self::once())->method('build')->with('category_banner', 'image')->willReturn($state);
        $result = $this->createStub(AttributeSynchronizationResultInterface::class);
        $result->method('isSuccessful')->willReturn(true);
        $attributeSynchronizer = $this->createMock(AttributeSynchronizerInterface::class);
        $attributeSynchronizer->expects(self::once())->method('synchronize')
            ->with($state, AttributeSynchronizerInterface::MODE_CREATE_ONLY)
            ->willReturnCallback(static function () use ($result, &$calls): AttributeSynchronizationResultInterface {
                $calls[] = 'attribute';
                return $result;
            });
        $registrySynchronizer = $this->createMock(CategoryAttributeRegistrySynchronizerInterface::class);
        $registrySynchronizer->expects(self::once())->method('ensure')->with('category_banner')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'registry';
            });
        $refresher = $this->createMock(SourceMetadata::class);
        $refresher->expects(self::once())->method('reset')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'refresh';
            });
        $rateLimitGuard = $this->createMock(SynchronizationRateLimitGuard::class);
        $rateLimitGuard->expects(self::once())->method('throwIfLimited')->with($result);
        $policy = $this->createStub(MappingProvider::class);
        $policy->method('getMagentoAttributes')->willReturn([['code' => 'category_banner']]);

        $publishedState = (new ErgonodeCategoryAttributeCreator(
            $builder,
            $policy,
            $attributeSynchronizer,
            $registrySynchronizer,
            $refresher,
            $rateLimitGuard
        ))->synchronizeFromMagento('category_banner', 'image');

        self::assertSame($state, $publishedState);
        self::assertSame(['attribute', 'registry', 'refresh'], $calls);
    }

    public function testRejectsNonMappableAttributeBeforeRemoteMutation(): void
    {
        $builder = $this->createMock(CategoryAttributeSourceStateBuilder::class);
        $builder->expects(self::never())->method('build');
        $policy = $this->createStub(MappingProvider::class);
        $policy->method('getMagentoAttributes')->willReturn([]);
        $creator = new ErgonodeCategoryAttributeCreator(
            $builder,
            $policy,
            $this->createStub(AttributeSynchronizerInterface::class),
            $this->createStub(CategoryAttributeRegistrySynchronizerInterface::class),
            $this->createStub(SourceMetadata::class),
            $this->createStub(SynchronizationRateLimitGuard::class)
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('path');
        $creator->synchronizeFromMagento('path', 'text');
    }
}
