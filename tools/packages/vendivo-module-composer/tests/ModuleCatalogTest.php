<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vendivo\ModuleComposer\ModuleCatalog;

final class ModuleCatalogTest extends TestCase
{
    #[DataProvider('packageNames')]
    public function testMapsMagentoModuleNamesToComposerPackageNames(string $module, string $package): void
    {
        self::assertSame($package, (new ModuleCatalog())->packageName($module));
    }

    /** @return iterable<string, array{string, string}> */
    public static function packageNames(): iterable
    {
        yield 'framework' => ['Magento_Framework', 'magento/framework'];
        yield 'GraphQL' => ['Vendivo_CatalogGraphQl', 'vendivo/module-catalog-graph-ql'];
        yield 'Admin UI' => ['Vendivo_BlogAdminUi', 'vendivo/module-blog-admin-ui'];
        yield 'Web API' => ['Vendivo_AttributeWebApi', 'vendivo/module-attribute-webapi'];
        yield 'URL rewrite' => ['Magento_UrlRewrite', 'magento/module-url-rewrite'];
    }

    public function testGlobalSelectionIgnoresEmptyVendorDirectories(): void
    {
        $root = sys_get_temp_dir() . '/vendivo-module-catalog-' . bin2hex(random_bytes(6));
        mkdir($root . '/app/code/Vendivo/Empty/Api', 0777, true);
        mkdir($root . '/app/code/Vendivo/Real/etc', 0777, true);
        file_put_contents($root . '/app/code/Vendivo/Real/etc/module.xml', '<module name="Vendivo_Real"/>');

        try {
            self::assertSame(
                [$root . '/app/code/Vendivo/Real'],
                (new ModuleCatalog())->select($root, null, null)
            );
        } finally {
            unlink($root . '/app/code/Vendivo/Real/etc/module.xml');
            rmdir($root . '/app/code/Vendivo/Real/etc');
            rmdir($root . '/app/code/Vendivo/Real');
            rmdir($root . '/app/code/Vendivo/Empty/Api');
            rmdir($root . '/app/code/Vendivo/Empty');
            rmdir($root . '/app/code/Vendivo');
            rmdir($root . '/app/code');
            rmdir($root . '/app');
            rmdir($root);
        }
    }

    public function testGlobalSelectionIncludesEveryProjectVendorButNotMagentoFixtures(): void
    {
        $root = sys_get_temp_dir() . '/vendivo-module-catalog-' . bin2hex(random_bytes(6));
        $vendors = ['Ergonode', 'PackHauer', 'Vendivo', 'Magento'];
        foreach ($vendors as $vendor) {
            mkdir($root . '/app/code/' . $vendor . '/Example/etc', 0777, true);
            file_put_contents(
                $root . '/app/code/' . $vendor . '/Example/etc/module.xml',
                '<module name="' . $vendor . '_Example"/>'
            );
        }

        try {
            self::assertSame([
                $root . '/app/code/Ergonode/Example',
                $root . '/app/code/PackHauer/Example',
                $root . '/app/code/Vendivo/Example',
            ], (new ModuleCatalog())->select($root, null, null));
        } finally {
            foreach ($vendors as $vendor) {
                $directory = $root . '/app/code/' . $vendor;
                unlink($directory . '/Example/etc/module.xml');
                rmdir($directory . '/Example/etc');
                rmdir($directory . '/Example');
                rmdir($directory);
            }
            rmdir($root . '/app/code');
            rmdir($root . '/app');
            rmdir($root);
        }
    }
}
