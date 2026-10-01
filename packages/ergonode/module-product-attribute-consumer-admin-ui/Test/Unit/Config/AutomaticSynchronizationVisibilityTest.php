<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Test\Unit\Config;

use DOMDocument;
use DOMXPath;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\CoreAdminUi\Plugin\AutomaticSynchronizationVisibility;
use Magento\Config\Model\Config\Structure\Element\Field;
use PHPUnit\Framework\TestCase;

class AutomaticSynchronizationVisibilityTest extends TestCase
{
    public function testOnlyActualCronFieldsAreHiddenInWriteMode(): void
    {
        $moduleRoot = dirname(__DIR__, 3);
        $system = new DOMDocument();
        self::assertTrue($system->load($moduleRoot . '/etc/adminhtml/system.xml'));
        $systemPaths = [];
        foreach ((new DOMXPath($system))->query(
            '/config/system/section[@id="ergonode_attributes"]/group[@id="cron"]/field'
        ) as $field) {
            $systemPaths[] = 'ergonode_attributes/cron/' . $field->attributes->getNamedItem('id')->nodeValue;
        }
        self::assertSame(['ergonode_attributes/cron/status', 'ergonode_attributes/cron/schedule'], $systemPaths);

        $di = new DOMDocument();
        self::assertTrue($di->load($moduleRoot . '/etc/adminhtml/di.xml'));
        $configuredPaths = [];
        foreach ((new DOMXPath($di))->query(
            '/config/type[@name="' . AutomaticSynchronizationVisibility::class . '"]'
            . '/arguments/argument[@name="paths"]/item'
        ) as $item) {
            $configuredPaths[] = trim($item->textContent);
        }

        $writeConfig = $this->createStub(ConfigProvider::class);
        $writeConfig->method('getMode')->willReturn('write');
        $writeVisibility = new AutomaticSynchronizationVisibility($writeConfig, $configuredPaths);
        $readConfig = $this->createStub(ConfigProvider::class);
        $readConfig->method('getMode')->willReturn('read');
        $readVisibility = new AutomaticSynchronizationVisibility($readConfig, $configuredPaths);

        foreach ($systemPaths as $path) {
            $field = $this->createStub(Field::class);
            $field->method('getPath')->willReturn($path);
            self::assertFalse($writeVisibility->afterIsVisible($field, true), $path);
            self::assertTrue($readVisibility->afterIsVisible($field, true), $path);
        }

        $manual = $this->createStub(Field::class);
        $manual->method('getPath')->willReturn('ergonode_attributes/options/delete_missing_magento_options');
        self::assertTrue($writeVisibility->afterIsVisible($manual, true));
        self::assertFalse($writeVisibility->afterIsVisible($manual, false));
    }
}
