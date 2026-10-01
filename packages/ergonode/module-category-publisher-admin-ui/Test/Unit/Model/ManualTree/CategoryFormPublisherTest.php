<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\Category\Api\CategoryCreationContextProviderInterface;
use Ergonode\Category\Api\CategoryLayoutSaverInterface;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCodeGenerator;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryFormPublisher;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryFormPublisherTest extends TestCase
{
    public function testReturnsExistingValidMappingWithoutPublishingAgain(): void
    {
        $contextProvider = $this->createStub(CategoryCreationContextProviderInterface::class);
        $contextProvider->method('getForMagentoCategory')->willReturn($this->context('chairs'));
        $batchPublisher = $this->createMock(CategoryBatchPublisher::class);
        $batchPublisher->expects(self::never())->method('publish');
        $layoutSaver = $this->createMock(CategoryLayoutSaverInterface::class);
        $layoutSaver->expects(self::never())->method('save');

        self::assertSame(
            'chairs',
            (new CategoryFormPublisher(
                $contextProvider,
                $batchPublisher,
                $layoutSaver,
                new CategoryCodeGenerator()
            ))->publish(12)
        );
    }

    public function testCreatesRemoteCategoryAndSavesCompleteTreeLayout(): void
    {
        $context = $this->context();
        $context['category']['label'] = 'New Luma Yoga Collection';
        $context['category']['url_key'] = 'yoga-new';
        $context['category']['path_labels'] = ['Collections', 'New Luma Yoga Collection'];
        $context['items'][0]['code'] = 'collections';
        $context['items'][0]['label'] = 'Collections';
        $contextProvider = $this->createMock(CategoryCreationContextProviderInterface::class);
        $contextProvider->expects(self::once())
            ->method('getForMagentoCategory')
            ->with(12)
            ->willReturn($context);
        $batchPublisher = $this->createMock(CategoryBatchPublisher::class);
        $batchPublisher->expects(self::once())->method('publish')->with(
            7,
            self::callback(
                static fn (array $items): bool => count($items) === 1
                    && $items[0]['code'] === 'collections__new_luma_yoga_collection'
                    && $items[0]['parent_code'] === 'collections'
                    && $items[0]['magento_category_id'] === 12
                    && $items[0]['extension_data']['to_ergonode']['pending_create'] === true
            ),
            []
        )->willReturn([[
            'code' => 'collections__new_luma_yoga_collection',
            'status' => 'synchronized',
            'message' => 'Created.',
            'remote_id' => 'remote-yoga',
        ]]);
        $layoutSaver = $this->createMock(CategoryLayoutSaverInterface::class);
        $layoutSaver->expects(self::once())->method('save')->with(7, self::callback(
            static function (array $items): bool {
                $created = $items[2] ?? [];

                return count($items) === 3
                    && $created['code'] === 'collections__new_luma_yoga_collection'
                    && $created['ergonode_category_id'] === 'remote-yoga'
                    && $created['extension_data']['to_ergonode']['remote_prepared'] === true;
            }
        ))->willReturn(['updated' => 1, 'unchanged' => 2]);

        self::assertSame(
            'collections__new_luma_yoga_collection',
            (new CategoryFormPublisher(
                $contextProvider,
                $batchPublisher,
                $layoutSaver,
                new CategoryCodeGenerator()
            ))->publish(12)
        );
    }

    public function testReusesPendingCodeAfterPartialRemoteSuccess(): void
    {
        $context = $this->context();
        $context['pending_code'] = 'chairs_retry';
        $contextProvider = $this->createStub(CategoryCreationContextProviderInterface::class);
        $contextProvider->method('getForMagentoCategory')->willReturn($context);
        $batchPublisher = $this->createMock(CategoryBatchPublisher::class);
        $batchPublisher->expects(self::once())->method('publish')->with(
            7,
            self::callback(
                static fn (array $items): bool => count($items) === 1
                    && $items[0]['code'] === 'chairs_retry'
            ),
            ['chairs_retry']
        )->willReturn([[
            'code' => 'chairs_retry',
            'status' => 'existing',
            'message' => 'Existing.',
            'remote_id' => 'remote-chairs',
        ]]);
        $layoutSaver = $this->createStub(CategoryLayoutSaverInterface::class);

        self::assertSame(
            'chairs_retry',
            (new CategoryFormPublisher(
                $contextProvider,
                $batchPublisher,
                $layoutSaver,
                new CategoryCodeGenerator()
            ))->publish(12)
        );
    }

    public function testReadableCodeCollisionIsNotResolvedWithNumericSuffix(): void
    {
        $context = $this->context();
        $context['category']['label'] = 'Promocja 10.99';
        $context['category']['url_key'] = 'promocja-10.99';
        $context['category']['path_labels'] = ['Furniture', 'Promocja 10.99'];
        $context['items'][] = [
            'code' => 'furniture__promocja_10_99',
            'label' => 'Existing promotion',
            'parent_code' => null,
            'sort_order' => 3,
            'magento_category_id' => null,
        ];
        $contextProvider = $this->createStub(CategoryCreationContextProviderInterface::class);
        $contextProvider->method('getForMagentoCategory')->willReturn($context);
        $batchPublisher = $this->createMock(CategoryBatchPublisher::class);
        $batchPublisher->expects(self::once())->method('publish')->with(
            7,
            self::callback(
                static fn (array $items): bool => count($items) === 1
                    && $items[0]['code'] === 'furniture__promocja_10_99'
                    && !str_contains($items[0]['code'], '_12_')
            ),
            []
        )->willReturn([[
            'code' => 'furniture__promocja_10_99',
            'status' => 'skipped',
            'message' => 'Code collision.',
        ]]);
        $layoutSaver = $this->createMock(CategoryLayoutSaverInterface::class);
        $layoutSaver->expects(self::never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Code collision.');
        (new CategoryFormPublisher(
            $contextProvider,
            $batchPublisher,
            $layoutSaver,
            new CategoryCodeGenerator()
        ))->publish(12);
    }

    /** @return array<string, mixed> */
    private function context(?string $mappedCode = null): array
    {
        return [
            'category_tree_id' => 7,
            'root_category_id' => 2,
            'mapped_code' => $mappedCode,
            'pending_code' => null,
            'category' => [
                'id' => 12,
                'label' => 'Chairs',
                'url_key' => 'chairs',
                'position' => 4,
                'path_ids' => [1, 2, 11, 12],
                'path_labels' => ['Furniture', 'Chairs'],
            ],
            'items' => [
                [
                    'code' => 'furniture',
                    'label' => 'Furniture',
                    'parent_code' => null,
                    'sort_order' => 1,
                    'magento_category_id' => 11,
                ],
                [
                    'code' => 'remote_chairs',
                    'label' => 'Remote chairs',
                    'parent_code' => 'furniture',
                    'sort_order' => 2,
                    'magento_category_id' => null,
                ],
            ],
        ];
    }
}
