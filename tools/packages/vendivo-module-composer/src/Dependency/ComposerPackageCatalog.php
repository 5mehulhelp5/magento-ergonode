<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Dependency;

use JsonException;

final class ComposerPackageCatalog
{
    /** @var array<string, array<string, string>> */
    private array $requires = [];

    /** @var array<string, string> */
    private array $namespaceOwners = [];

    /** @var array<string, true> */
    private array $localPackages = [];

    /** @var array<string, string> */
    private array $rootRequires = [];

    public function __construct(private readonly string $rootDirectory)
    {
        $this->loadRootRequires();
        $this->loadInstalledPackages();
        $this->loadLocalModules();
        uksort(
            $this->namespaceOwners,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left)
                ?: strcmp($left, $right)
        );
    }

    public function packageForClass(string $class): ?string
    {
        $class = ltrim($class, '\\');
        foreach ($this->namespaceOwners as $prefix => $package) {
            if (str_starts_with($class, $prefix)) {
                return $package;
            }
        }

        return null;
    }

    /** @param list<string> $directPackages */
    public function isSatisfiedBy(array $directPackages, string $targetPackage): bool
    {
        foreach ($directPackages as $directPackage) {
            if ($directPackage === $targetPackage || $this->reaches($directPackage, $targetPackage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $directPackages
     * @return array<string, string>
     */
    public function redundantPackages(array $directPackages): array
    {
        $redundant = [];
        foreach ($directPackages as $package) {
            foreach ($directPackages as $candidateProvider) {
                if ($candidateProvider === $package || !$this->reaches($candidateProvider, $package)) {
                    continue;
                }

                if ($this->reaches($package, $candidateProvider)
                    && strcmp($candidateProvider, $package) > 0
                ) {
                    continue;
                }

                $redundant[$package] = $candidateProvider;
                break;
            }
        }

        ksort($redundant);

        return $redundant;
    }

    public function constraintFor(string $package): string
    {
        if (str_starts_with($package, 'magento/')) {
            return '*';
        }
        if (isset($this->localPackages[$package])) {
            return 'dev-main@dev';
        }

        return $this->rootRequires[$package] ?? '*';
    }

    private function reaches(string $sourcePackage, string $targetPackage): bool
    {
        $pending = array_keys($this->requires[$sourcePackage] ?? []);
        $visited = [];
        while ($pending !== []) {
            $candidate = array_pop($pending);
            if (!is_string($candidate) || isset($visited[$candidate])) {
                continue;
            }
            if ($candidate === $targetPackage) {
                return true;
            }

            $visited[$candidate] = true;
            array_push($pending, ...array_keys($this->requires[$candidate] ?? []));
        }

        return false;
    }

    private function loadRootRequires(): void
    {
        $composer = $this->readJson($this->rootDirectory . '/composer.json');
        foreach ($composer['require'] ?? [] as $package => $constraint) {
            if (is_string($package) && is_string($constraint)) {
                $this->rootRequires[$package] = $constraint;
            }
        }
        foreach ($composer['require-dev'] ?? [] as $package => $constraint) {
            if (is_string($package) && is_string($constraint)) {
                $this->rootRequires[$package] = $constraint;
            }
        }
    }

    private function loadInstalledPackages(): void
    {
        $installed = $this->readJson($this->rootDirectory . '/vendor/composer/installed.json');
        $packages = is_array($installed['packages'] ?? null) ? $installed['packages'] : $installed;
        foreach ($packages as $package) {
            if (is_array($package)) {
                $this->registerPackage($package, false);
            }
        }
    }

    private function loadLocalModules(): void
    {
        $composerFiles = array_merge(glob($this->rootDirectory . '/app/code/*/*/composer.json') ?: [], glob($this->rootDirectory . '/packages/*/*/composer.json') ?: []);
        sort($composerFiles);
        foreach ($composerFiles as $composerFile) {
            $this->registerPackage($this->readJson($composerFile), true);
        }
    }

    /** @param array<string, mixed> $composer */
    private function registerPackage(array $composer, bool $local): void
    {
        $name = $composer['name'] ?? null;
        if (!is_string($name) || $name === '') {
            return;
        }

        $requires = [];
        foreach ($composer['require'] ?? [] as $package => $constraint) {
            if ($this->isPackageDependency($package) && is_string($constraint)) {
                $requires[$package] = $constraint;
            }
        }
        $this->requires[$name] = $requires;
        if ($local) {
            $this->localPackages[$name] = true;
        }

        $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
        foreach (['psr-4', 'psr-0'] as $autoloadType) {
            $prefixes = is_array($autoload[$autoloadType] ?? null) ? $autoload[$autoloadType] : [];
            foreach (array_keys($prefixes) as $prefix) {
                if (is_string($prefix) && $prefix !== '') {
                    $this->namespaceOwners[ltrim($prefix, '\\')] = $name;
                }
            }
        }
    }

    private function isPackageDependency(mixed $package): bool
    {
        return is_string($package)
            && str_contains($package, '/')
            && !str_starts_with($package, 'ext-')
            && !str_starts_with($package, 'lib-');
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
