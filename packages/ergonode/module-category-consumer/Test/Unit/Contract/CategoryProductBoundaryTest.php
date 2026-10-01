<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Contract;

use JsonException;
use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class CategoryProductBoundaryTest extends TestCase
{
    /** @throws JsonException */
    public function testCategoryModulesDoNotTransitivelyDependOnProductModules(): void
    {
        $dependencies = $this->moduleDependencies();
        $categoryPackages = array_filter(
            array_keys($dependencies),
            static fn (string $package): bool => str_starts_with($package, 'ergonode/module-category')
        );

        foreach ($categoryPackages as $categoryPackage) {
            $visited = [];
            $pending = [$categoryPackage];
            while ($pending !== []) {
                $package = array_pop($pending);
                if (isset($visited[$package])) {
                    continue;
                }
                $visited[$package] = true;
                self::assertFalse(
                    str_starts_with($package, 'ergonode/module-product'),
                    sprintf('%s depends on %s.', $categoryPackage, $package)
                );
                foreach ($dependencies[$package] ?? [] as $dependency) {
                    $pending[] = $dependency;
                }
            }
        }
    }

    /**
     * @return array<string, string[]>
     * @throws JsonException
     */
    private function moduleDependencies(): array
    {
        $dependencies = [];
        foreach ((new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE) as $module => $root) {
            if (!str_starts_with($module, 'Ergonode_')) {
                continue;
            }
            $composerFile = $root . '/composer.json';
            if (!is_file($composerFile)) {
                continue;
            }
            $composer = json_decode(
                (string)file_get_contents($composerFile),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $package = (string)($composer['name'] ?? '');
            if (!str_starts_with($package, 'ergonode/module-')) {
                continue;
            }
            $dependencies[$package] = array_values(array_filter(
                array_keys($composer['require'] ?? []),
                static fn (string $dependency): bool => str_starts_with(
                    $dependency,
                    'ergonode/module-'
                )
            ));
        }

        return $dependencies;
    }
}
