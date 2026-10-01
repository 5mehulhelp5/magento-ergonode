<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Integration\Model\Import;

use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Import\OptionCacheReconciler;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class OptionCacheReconcilerTest extends TestCase
{
    public function testPruningPreservesZeroAndDoesNotTouchOtherAttributes(): void
    {
        $objects = Bootstrap::getObjectManager();
        $writer = $objects->get(AttributeCacheWriter::class);
        $provider = $objects->get(ErgonodeOptionProviderInterface::class);
        $reconciler = $objects->get(OptionCacheReconciler::class);
        $options = [];
        foreach (['0', '1', 'obsolete'] as $index => $code) {
            $options[] = [
                'code' => $code, 'labels' => ['en_US' => $code], 'sort_order' => $index,
                'hash' => hash('sha256', $code),
            ];
        }
        $writer->saveOptions('audit_zero', $options);
        $writer->saveOptions('audit_other', $options);
        self::assertCount(3, $provider->getOptionDefinitions('audit_zero'));
        self::assertSame(1, $reconciler->reconcile('audit_zero', ['0', '1']));
        self::assertSame(['0', '1'], array_column($provider->getOptionDefinitions('audit_zero'), 'code'));
        self::assertSame(1, $reconciler->reconcile('audit_zero', ['0']));
        self::assertSame(['0'], array_column($provider->getOptionDefinitions('audit_zero'), 'code'));
        self::assertCount(3, $provider->getOptionDefinitions('audit_other'));
    }
}
