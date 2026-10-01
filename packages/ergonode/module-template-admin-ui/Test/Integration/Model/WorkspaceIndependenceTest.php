<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Test\Integration\Model;

use Ergonode\TemplateAdminUi\Model\TemplateUiProvider;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class WorkspaceIndependenceTest extends TestCase
{
    public function testBaseWorkspaceReadsSnapshotAndProductSetsInTheGlobalArea(): void
    {
        $provider = Bootstrap::getObjectManager()->get(TemplateUiProvider::class);
        self::assertIsArray($provider->getTemplates());
        $sets = $provider->getAttributeSets();
        self::assertNotEmpty($sets);
        self::assertArrayHasKey('id', $sets[0]);
        self::assertArrayHasKey('name', $sets[0]);
    }
}
