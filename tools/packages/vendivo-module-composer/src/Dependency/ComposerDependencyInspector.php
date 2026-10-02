<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Dependency;

use FilesystemIterator;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Vendivo\ModuleComposer\Diagnostic;

final class ComposerDependencyInspector
{
    private readonly Parser $parser;

    /** @var array<string, ComposerPackageCatalog> */
    private array $catalogs = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @param array<string, mixed> $require
     * @return list<Diagnostic>
     */
    public function validate(
        string $rootDirectory,
        string $moduleDirectory,
        string $composerPath,
        string $currentPackage,
        array $require
    ): array {
        $catalog = $this->catalog($rootDirectory);
        $analysis = $this->analyze($rootDirectory, $moduleDirectory, $currentPackage, $catalog);
        $directPackages = $this->directPackages($require);
        $diagnostics = $analysis['diagnostics'];

        foreach ($analysis['packages'] as $package => $evidence) {
            if (in_array($package, $directPackages, true)) {
                continue;
            }

            $diagnostics[] = Diagnostic::error(
                'COMPOSER-DEPENDENCY-MISSING',
                $composerPath,
                sprintf(
                    '%s uses %s from %s, but that package is not declared directly in require.',
                    $evidence['file'],
                    $evidence['class'],
                    $package
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, string> $require
     * @return array<string, string>
     */
    public function normalizeRequires(
        string $rootDirectory,
        string $moduleDirectory,
        string $currentPackage,
        array $require
    ): array {
        $catalog = $this->catalog($rootDirectory);
        $analysis = $this->analyze($rootDirectory, $moduleDirectory, $currentPackage, $catalog);
        $directPackages = $this->directPackages($require);
        foreach (array_keys($analysis['packages']) as $package) {
            if (!in_array($package, $directPackages, true)) {
                $require[$package] = $catalog->constraintFor($package);
                $directPackages[] = $package;
            }
        }

        return $require;
    }

    /**
     * @return array{
     *   packages: array<string, array{class: string, file: string}>,
     *   diagnostics: list<Diagnostic>
     * }
     */
    private function analyze(
        string $rootDirectory,
        string $moduleDirectory,
        string $currentPackage,
        ComposerPackageCatalog $catalog
    ): array {
        $packages = [];
        $diagnostics = [];
        foreach ($this->sourceFiles($moduleDirectory) as $file) {
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['php', 'phtml', 'xml', 'graphqls'], true)) {
                continue;
            }

            $path = $file->getPathname();
            $relativePath = str_starts_with($path, $rootDirectory . '/')
                ? substr($path, strlen($rootDirectory) + 1)
                : $path;
            $contents = (string)file_get_contents($path);
            try {
                $names = in_array($extension, ['xml', 'graphqls'], true)
                    ? $this->serializedNames($contents)
                    : array_values(array_unique(array_merge(
                        $this->phpNames($contents),
                        $this->serializedNames($contents)
                    )));
            } catch (Error $error) {
                $diagnostics[] = Diagnostic::error(
                    'COMPOSER-SOURCE-PARSE',
                    $relativePath,
                    $error->getRawMessage()
                );
                continue;
            }

            foreach ($names as $class) {
                $package = $catalog->packageForClass($class);
                if ($package === null || $package === $currentPackage || isset($packages[$package])) {
                    continue;
                }
                $packages[$package] = ['class' => $class, 'file' => $relativePath];
            }
        }
        ksort($packages);

        return ['packages' => $packages, 'diagnostics' => $diagnostics];
    }

    /** @return list<string> */
    private function phpNames(string $contents): array
    {
        $statements = $this->parser->parse($contents) ?? [];
        $collector = new ReferencedNameCollector();
        $traverser = new NodeTraverser(new NameResolver(), $collector);
        $traverser->traverse($statements);

        return $collector->names();
    }

    /** @return list<string> */
    private function serializedNames(string $contents): array
    {
        $contents = str_replace('\\\\', '\\', $contents);
        preg_match_all(
            '/(?<![A-Za-z0-9_\\\\])(?:[A-Z][A-Za-z0-9_]*\\\\){2,}[A-Z][A-Za-z0-9_]*(?![A-Za-z0-9_\\\\])/',
            $contents,
            $matches
        );
        $names = array_values(array_unique($matches[0] ?? []));
        sort($names);

        return $names;
    }

    /** @return list<SplFileInfo> */
    private function sourceFiles(string $moduleDirectory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleDirectory, FilesystemIterator::SKIP_DOTS)
        );
        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && !$this->isTestFile($file->getPathname())) {
                $files[] = $file;
            }
        }
        usort(
            $files,
            static fn (SplFileInfo $left, SplFileInfo $right): int => strcmp(
                $left->getPathname(),
                $right->getPathname()
            )
        );

        return $files;
    }

    /** @param array<string, mixed> $require @return list<string> */
    private function directPackages(array $require): array
    {
        $packages = [];
        foreach ($require as $package => $constraint) {
            if (is_string($package)
                && is_string($constraint)
                && str_contains($package, '/')
                && !str_starts_with($package, 'ext-')
                && !str_starts_with($package, 'lib-')
            ) {
                $packages[] = $package;
            }
        }
        sort($packages);

        return array_values(array_unique($packages));
    }

    private function catalog(string $rootDirectory): ComposerPackageCatalog
    {
        return $this->catalogs[$rootDirectory] ??= new ComposerPackageCatalog($rootDirectory);
    }

    private function isTestFile(string $path): bool
    {
        return str_contains(str_replace('\\', '/', $path), '/Test/');
    }
}
