<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Plugin;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\AttributeConsumer\Api\OptionSnapshotRemoverInterface;
use Ergonode\ProductAttribute\Model\Mapping\OptionMappingSaver;
use Ergonode\ProductAttributeHistory\Api\HistoryOperationCaptureInterface;
use Ergonode\ProductAttributeHistory\Plugin\OptionMappingSaveHistoryPlugin;
use Ergonode\ProductAttributeHistory\Plugin\OptionRefreshHistoryPlugin;
use Ergonode\ProductAttributeHistory\Plugin\OptionRemovalHistoryPlugin;
use PHPUnit\Framework\TestCase;

class OptionCapturePluginsTest extends TestCase
{
    public function testOptionBoundariesPreserveArgumentsResultsAndCaptureCodes(): void
    {
        $codes = [];
        $capture = $this->createMock(HistoryOperationCaptureInterface::class);
        $capture->expects(self::exactly(3))->method('execute')->willReturnCallback(
            static function (string $code, callable $operation) use (&$codes): mixed {
                $codes[] = $code;
                return $operation();
            }
        );
        $mappings = [['left' => ['code' => 'yes'], 'right' => ['code' => 'option_1']]];
        $visibility = [['source' => 'ergo', 'code' => 'yes', 'active' => false]];
        $result = (new OptionMappingSaveHistoryPlugin($capture))->aroundSave(
            $this->createStub(OptionMappingSaver::class),
            static function (int $id, array $items, array $active) use ($mappings, $visibility): array {
                self::assertSame(7, $id);
                self::assertSame($mappings, $items);
                self::assertSame($visibility, $active);
                return ['inserted' => 1];
            },
            7,
            $mappings,
            $visibility
        );
        self::assertSame(['inserted' => 1], $result);
        (new OptionRefreshHistoryPlugin($capture))->aroundRefreshOptions(
            $this->createStub(AttributeCacheRefresherInterface::class),
            static function (string $code): void {
                self::assertSame('color', $code);
            },
            'color'
        );
        (new OptionRemovalHistoryPlugin($capture))->aroundRemove(
            $this->createStub(OptionSnapshotRemoverInterface::class),
            static function (string $parent, string $code): void {
                self::assertSame('color', $parent);
                self::assertSame('red', $code);
            },
            'color',
            'red'
        );
        self::assertSame(['save_options', 'refresh_options', 'delete_option_snapshot'], $codes);
    }
}
