<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Validation;

use JsonException;
use Vendivo\ModuleComposer\Dependency\ComposerDependencyInspector;
use Vendivo\ModuleComposer\Diagnostic;
use Vendivo\ModuleComposer\ModuleCatalog;

final class ModuleMetadataValidator
{
    public const AUTHOR_NAME = 'Pack Hauer';

    public const AUTHOR_EMAIL = 'packhauer@gmail.com';

    public const PHP_CONSTRAINT = '~8.3.0||~8.4.0||~8.5.0';

    public const INTERNAL_CONSTRAINT = 'dev-main@dev';

    public const MAGENTO_CONSTRAINT = '*';

    public const MODULE_LIFECYCLE = 'development';

    public function __construct(
        private readonly ModuleCatalog $moduleCatalog,
        private readonly ComposerSchemaValidator $schemaValidator,
        private readonly ComposerDependencyInspector $dependencyInspector
    ) {
    }

    /** @return list<Diagnostic> */
    public function validate(
        string $rootDirectory,
        string $moduleDirectory,
        ?string $explicitModuleName = null
    ): array {
        $moduleName = $explicitModuleName ?? $this->moduleCatalog->moduleName($moduleDirectory);
        $composerPath = $moduleDirectory . '/composer.json';
        $relativePath = $this->moduleCatalog->relativePath($rootDirectory, $composerPath);
        if (!is_file($composerPath)) {
            return [Diagnostic::error(
                'COMPOSER-MISSING',
                $relativePath,
                'composer.json is required for every project module.'
            )];
        }

        $diagnostics = $this->schemaValidator->validate($composerPath, $relativePath);
        try {
            $composer = $this->readJson($composerPath);
        } catch (JsonException) {
            return $diagnostics;
        }

        $this->assertValue(
            $diagnostics,
            $relativePath,
            'name',
            $composer['name'] ?? null,
            $this->moduleCatalog->packageName($moduleName)
        );
        $this->assertNonEmpty($diagnostics, $relativePath, 'description', $composer['description'] ?? null);
        $this->assertValue($diagnostics, $relativePath, 'type', $composer['type'] ?? null, 'magento2-module');
        $this->assertValue($diagnostics, $relativePath, 'license', $composer['license'] ?? null, 'proprietary');

        $authors = is_array($composer['authors'] ?? null) ? $composer['authors'] : [];
        $author = is_array($authors[0] ?? null) ? $authors[0] : [];
        $this->assertValue(
            $diagnostics,
            $relativePath,
            'authors[0].name',
            $author['name'] ?? null,
            self::AUTHOR_NAME
        );
        $this->assertValue(
            $diagnostics,
            $relativePath,
            'authors[0].email',
            $author['email'] ?? null,
            self::AUTHOR_EMAIL
        );

        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $metadataKey = $this->moduleCatalog->moduleVendor($moduleName);
        $moduleExtra = is_array($extra[$metadataKey] ?? null) ? $extra[$metadataKey] : [];
        $this->assertValue(
            $diagnostics,
            $relativePath,
            'extra.' . $metadataKey . '.lifecycle',
            $moduleExtra['lifecycle'] ?? null,
            self::MODULE_LIFECYCLE
        );

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $this->assertValue(
            $diagnostics,
            $relativePath,
            'require.php',
            $require['php'] ?? null,
            self::PHP_CONSTRAINT
        );
        foreach ($require as $package => $constraint) {
            if (str_starts_with((string)$package, 'magento/')) {
                $this->assertValue(
                    $diagnostics,
                    $relativePath,
                    'require.' . $package,
                    $constraint,
                    self::MAGENTO_CONSTRAINT
                );
            }
        }

        array_push(
            $diagnostics,
            ...$this->dependencyInspector->validate(
                $rootDirectory,
                $moduleDirectory,
                $relativePath,
                $this->moduleCatalog->packageName($moduleName),
                $require
            )
        );

        $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
        $files = is_array($autoload['files'] ?? null) ? $autoload['files'] : [];
        if ($files !== ['registration.php']) {
            $diagnostics[] = Diagnostic::error(
                'COMPOSER-AUTOLOAD',
                $relativePath,
                'autoload.files must be exactly ["registration.php"].'
            );
        }

        $psr4 = is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];
        $expectedNamespace = str_replace('_', '\\', $moduleName) . '\\';
        $this->assertValue(
            $diagnostics,
            $relativePath,
            'autoload.psr-4.' . $expectedNamespace,
            $psr4[$expectedNamespace] ?? null,
            '',
            'COMPOSER-AUTOLOAD'
        );

        array_push($diagnostics, ...$this->lifecycleDiagnostics($relativePath, $moduleName, $composer));

        return $diagnostics;
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    private function assertValue(
        array &$diagnostics,
        string $path,
        string $field,
        mixed $actual,
        string $expected,
        string $code = 'COMPOSER-METADATA'
    ): void {
        if ($actual === $expected) {
            return;
        }

        $diagnostics[] = Diagnostic::error($code, $path, sprintf(
            '%s must be "%s", got "%s".',
            $field,
            $expected,
            is_scalar($actual) ? (string)$actual : get_debug_type($actual)
        ));
    }

    /** @param list<Diagnostic> $diagnostics */
    private function assertNonEmpty(array &$diagnostics, string $path, string $field, mixed $actual): void
    {
        if (is_string($actual) && trim($actual) !== '') {
            return;
        }

        $diagnostics[] = Diagnostic::error(
            'COMPOSER-METADATA',
            $path,
            sprintf('%s must be a non-empty string.', $field)
        );
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $composer
     * @return list<Diagnostic>
     */
    private function lifecycleDiagnostics(string $path, string $moduleName, array $composer): array
    {
        $diagnostics = [];
        if (array_key_exists('version', $composer)) {
            $diagnostics[] = Diagnostic::warning(
                'COMPOSER-LEGACY',
                $path,
                sprintf('%s embeds a version; remove it in the owning module task.', $moduleName)
            );
        }

        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $developmentStatus = is_string($extra['development-status'] ?? null)
            ? $extra['development-status']
            : null;
        if ($developmentStatus !== null && !in_array($developmentStatus, ['alpha', 'beta', 'RC'], true)) {
            $diagnostics[] = Diagnostic::error(
                'COMPOSER-DEVELOPMENT-STATUS',
                $path,
                sprintf('%s has unsupported extra.development-status=%s.', $moduleName, $developmentStatus)
            );
        }

        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        foreach ($require as $package => $constraint) {
            if (!$this->isInternalPackage((string)$package) || !is_string($constraint)) {
                continue;
            }
            if (in_array($constraint, [self::INTERNAL_CONSTRAINT, '0.1.*'], true)) {
                continue;
            }

            $diagnostics[] = Diagnostic::error(
                'COMPOSER-INTERNAL-CONSTRAINT',
                $path,
                sprintf(
                    '%s requires %s with unsupported in-repository constraint %s.',
                    $moduleName,
                    $package,
                    $constraint
                )
            );
        }

        return $diagnostics;
    }

    private function isInternalPackage(string $package): bool
    {
        return str_starts_with($package, 'vendivo/') || str_starts_with($package, 'packhauer/');
    }
}
