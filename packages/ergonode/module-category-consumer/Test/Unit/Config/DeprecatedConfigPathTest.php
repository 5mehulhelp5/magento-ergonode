<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Config;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class DeprecatedConfigPathTest extends TestCase
{
    public function testRuntimeDoesNotReadDeprecatedCategoryConfigPaths(): void
    {
        $deprecatedPaths = [
            'ergonode/category/tree_code',
            'ergonode/category/root_category_id',
            'ergonode/category/page_size',
            'ergonode/category/sync_after_import',
            'ergonode/category/remove_missing',
            'ergonode/category/delete_missing_magento',
        ];
        $violations = [];

        foreach (['Core', 'CoreAdminUi', 'CategoryConsumer', 'CategoryConsumerAdminUi'] as $module) {
            $moduleRoot = (new ComponentRegistrar())->getPath(
                ComponentRegistrar::MODULE,
                'Ergonode_' . $module
            );
            self::assertNotNull($moduleRoot, $module);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($moduleRoot)
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if (!$file->isFile()
                    || !in_array($file->getExtension(), ['php', 'xml', 'js', 'phtml'], true)
                    || str_contains($path, '/Test/')
                    || str_contains($path, '/Setup/Patch/Data/')
                ) {
                    continue;
                }

                $source = (string)file_get_contents($path);
                foreach ($deprecatedPaths as $deprecatedPath) {
                    if (str_contains($source, $deprecatedPath)) {
                        $violations[] = $path . ': ' . $deprecatedPath;
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }
}
