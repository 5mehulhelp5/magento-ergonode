<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Plugin;

use Ergonode\CoreAdminUi\Plugin\AutomaticSynchronizationVisibility;
use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Config\Model\Config\Structure\Element\Field;
use PHPUnit\Framework\TestCase;

class AutomaticSynchronizationVisibilityTest extends TestCase
{
    public function testWriteModeHidesOnlyRegisteredAutomationFields(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getMode')->willReturn('write');
        $plugin = new AutomaticSynchronizationVisibility($config, ['ergonode_categories/cron/status']);
        $field = $this->createStub(Field::class);
        $field->method('getPath')->willReturn('ergonode_categories/cron/status');
        self::assertFalse($plugin->afterIsVisible($field, true));
        $manual = $this->createStub(Field::class);
        $manual->method('getPath')->willReturn('ergonode_categories/cron/initial_page_size');
        self::assertTrue($plugin->afterIsVisible($manual, true));
        self::assertFalse($plugin->afterIsVisible($manual, false));
    }
}
