<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Plugin;

use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreePublisher;
use Ergonode\CategoryPublisherAdminUi\Plugin\CategoryLayoutSaverPlugin;
use PHPUnit\Framework\TestCase;
use Ergonode\Category\Model\Mapping\CategoryLayoutValidator;
use Magento\Framework\Exception\LocalizedException;

class CategoryLayoutSaverPluginTest extends TestCase
{
    public function testLocalMappingSaveDoesNotUseManualTreePublisher(): void
    {
        $publisher = $this->createMock(CategoryTreePublisher::class);
        $publisher->expects(self::once())->method('requiresManualWrite')->willReturn(false);
        $publisher->expects(self::never())->method('publish');
        $result = (new CategoryLayoutSaverPlugin(
            $publisher,
            $this->createStub(CategoryLayoutValidator::class)
        ))->aroundSave(
            $this->createStub(CategoryLayoutSaver::class),
            static fn (int $treeId, array $payload, array $visibility): array => [
                'updated' => $treeId,
                'unchanged' => count($payload) + count($visibility),
            ],
            3,
            [['code' => 'chairs']],
            [['identifier' => 'chairs']]
        );

        self::assertSame(['updated' => 3, 'unchanged' => 2], $result);
    }

    public function testManualTreeWriteRunsBeforeLocalSave(): void
    {
        $items = [[
            'code' => 'chairs',
            'extension_data' => ['to_ergonode' => ['pending_create' => true]],
        ]];
        $calls = [];
        $publisher = $this->createMock(CategoryTreePublisher::class);
        $publisher->expects(self::once())->method('requiresManualWrite')->with(3, $items)->willReturn(true);
        $publisher->expects(self::once())->method('publish')->with(3, $items)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'remote';
            });

        $publisher->expects(self::once())->method('complete')->with(3, $items);
        (new CategoryLayoutSaverPlugin(
            $publisher,
            $this->createStub(CategoryLayoutValidator::class)
        ))->aroundSave(
            $this->createStub(CategoryLayoutSaver::class),
            static function () use (&$calls): array {
                $calls[] = 'local';

                return [];
            },
            3,
            $items
        );

        self::assertSame(['remote', 'local'], $calls);
    }
    public function testInvalidLayoutHasNoRemoteOrLocalEffects(): void
    {
        $validator = $this->createMock(CategoryLayoutValidator::class);
        $validator->expects(self::once())->method('validate')
            ->willThrowException(new LocalizedException(__('Invalid mapping')));
        $publisher = $this->createMock(CategoryTreePublisher::class);
        $publisher->expects(self::never())->method('requiresManualWrite');
        $publisher->expects(self::never())->method('publish');
        $publisher->expects(self::never())->method('complete');
        $this->expectException(LocalizedException::class);
        (new CategoryLayoutSaverPlugin($publisher, $validator))->aroundSave(
            $this->createStub(CategoryLayoutSaver::class),
            static function (): array {
                self::fail('Invalid input reached local persistence.');
            },
            3,
            [['code' => 'chairs']]
        );
    }
}
