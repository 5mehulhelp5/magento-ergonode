<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Generation;

use JsonException;
use RuntimeException;
use Vendivo\ModuleComposer\Dependency\ComposerDependencyInspector;
use Vendivo\ModuleComposer\ModuleCatalog;
use Vendivo\ModuleComposer\Validation\ModuleMetadataValidator;

final class ComposerGenerator
{
    public function __construct(
        private readonly ModuleCatalog $moduleCatalog,
        private readonly ComposerDependencyInspector $dependencyInspector
    ) {
    }

    public function generate(
        string $rootDirectory,
        string $moduleDirectory,
        ?string $description,
        ?string $family,
        bool $force,
        bool $dryRun
    ): string {
        $moduleName = $this->moduleCatalog->moduleName($moduleDirectory);
        $composerPath = $moduleDirectory . '/composer.json';
        $relativePath = $this->moduleCatalog->relativePath($rootDirectory, $composerPath);
        $description = $description !== null && trim($description) === '' ? null : $description;

        if (is_file($composerPath) && !$force && !$dryRun) {
            throw new RuntimeException(sprintf(
                'composer.json already exists for %s. Use --force to overwrite.',
                $moduleName
            ));
        }

        $existing = is_file($composerPath) ? $this->readJson($composerPath) : [];
        $description ??= is_string($existing['description'] ?? null)
            ? $existing['description']
            : str_replace('_', ' ', $moduleName) . ' Magento module.';

        $require = [
            'php' => ModuleMetadataValidator::PHP_CONSTRAINT,
        ];
        if (is_array($existing['require'] ?? null)) {
            foreach ($existing['require'] as $package => $constraint) {
                if ($package === 'php' || !is_string($constraint)) {
                    continue;
                }

                $require[(string)$package] = str_starts_with((string)$package, 'magento/')
                    ? ModuleMetadataValidator::MAGENTO_CONSTRAINT
                    : $constraint;
            }
        }

        $require = $this->dependencyInspector->normalizeRequires(
            $rootDirectory,
            $moduleDirectory,
            $this->moduleCatalog->packageName($moduleName),
            $require
        );
        ksort($require);
        $require = ['php' => ModuleMetadataValidator::PHP_CONSTRAINT]
            + array_diff_key($require, ['php' => true]);

        $metadataKey = $this->moduleCatalog->moduleVendor($moduleName);
        $composer = [
            'name' => $this->moduleCatalog->packageName($moduleName),
            'description' => $description,
            'type' => 'magento2-module',
            'license' => 'proprietary',
            'authors' => [
                [
                    'name' => ModuleMetadataValidator::AUTHOR_NAME,
                    'email' => ModuleMetadataValidator::AUTHOR_EMAIL,
                ],
            ],
            'require' => $require,
            'autoload' => [
                'files' => ['registration.php'],
                'psr-4' => [str_replace('_', '\\', $moduleName) . '\\' => ''],
            ],
            'extra' => [
                $metadataKey => [
                    'lifecycle' => ModuleMetadataValidator::MODULE_LIFECYCLE,
                ],
            ],
        ];

        $existingExtra = is_array($existing['extra'] ?? null) ? $existing['extra'] : [];
        $existingModuleExtra = is_array($existingExtra[$metadataKey] ?? null) ? $existingExtra[$metadataKey] : [];
        $packageFamily = $family ?? (is_string($existingModuleExtra['package-family'] ?? null)
            ? $existingModuleExtra['package-family']
            : null);
        if ($packageFamily !== null && $packageFamily !== '') {
            $composer['extra'][$metadataKey]['package-family'] = $packageFamily;
        }

        $json = json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        if ($dryRun) {
            return sprintf("DRY-RUN [MODULE-COMPOSER-GENERATE] %s\n%s", $relativePath, $json);
        }

        if (file_put_contents($composerPath, $json) === false) {
            throw new RuntimeException(sprintf('Unable to write %s.', $relativePath));
        }

        return sprintf("WROTE [MODULE-COMPOSER-GENERATE] %s\n", $relativePath);
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('%s is not valid JSON.', $path), 0, $exception);
        }

        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
