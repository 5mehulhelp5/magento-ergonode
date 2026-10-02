<?php
/**
 * Copyright 2026 Vendivo.
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Vendivo\Sniffs\GeneratedFactory;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class MissingExplicitFactorySniff implements Sniff
{
    private const ERROR_CODE = 'MissingExplicitFactory';

    /**
     * @var string[]
     */
    public array $projectPrefixes = [
        'Ergonode\\',
        'PackHauer\\',
    ];

    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isSkippedFile($phpcsFile->getFilename())) {
            return;
        }

        $namespace = $this->readNamespace($phpcsFile);
        $useMap = $this->collectUseMap($phpcsFile);
        $useStatementRanges = $this->collectUseStatementRanges($phpcsFile);
        $reported = [];
        $tokens = $phpcsFile->getTokens();

        foreach ($tokens as $ptr => $token) {
            if (!in_array($token['code'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            if ($this->isInsideUseStatement($ptr, $useStatementRanges)) {
                continue;
            }

            if (!$this->looksLikeFactoryReference($token['content'])) {
                continue;
            }

            if ($this->isDeclarationName($phpcsFile, $ptr) || $this->isMemberAccess($phpcsFile, $ptr)) {
                continue;
            }

            $factoryClass = $this->resolveClassName($token['content'], $namespace, $useMap);
            if (!$this->isProjectFactory($factoryClass)) {
                continue;
            }

            if (isset($reported[$factoryClass]) || $this->factoryFileExists($phpcsFile, $factoryClass)) {
                continue;
            }

            $reported[$factoryClass] = true;
            $phpcsFile->addError(
                'Project factory %s is referenced, but its PHP file is missing at %s. ' .
                'Do not rely on Magento-generated factories for project code. ' .
                'Create an explicit factory class in the repository or replace the dependency.',
                $ptr,
                self::ERROR_CODE,
                [
                    $factoryClass,
                    $this->expectedFactoryPath($phpcsFile, $factoryClass),
                ]
            );
        }
    }

    private function isSkippedFile(string $filename): bool
    {
        $normalized = str_replace('\\', '/', $filename);

        return str_contains($normalized, '/generated/code/')
            || str_contains($normalized, '/vendor/')
            || str_contains($normalized, '/Test/')
            || str_contains($normalized, '/dev/tests/');
    }

    private function looksLikeFactoryReference(string $content): bool
    {
        $normalized = trim($content, '\\');

        return str_ends_with($normalized, 'Factory');
    }

    private function isDeclarationName(File $phpcsFile, int $ptr): bool
    {
        $previous = $phpcsFile->findPrevious(T_WHITESPACE, $ptr - 1, null, true);
        if ($previous === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        return in_array($tokens[$previous]['code'], [T_CLASS, T_INTERFACE, T_TRAIT], true);
    }

    private function isMemberAccess(File $phpcsFile, int $ptr): bool
    {
        $previous = $phpcsFile->findPrevious(T_WHITESPACE, $ptr - 1, null, true);
        if ($previous === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        return in_array($tokens[$previous]['code'], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true);
    }

    private function readNamespace(File $phpcsFile): string
    {
        $tokens = $phpcsFile->getTokens();
        $namespacePtr = $phpcsFile->findNext(T_NAMESPACE, 0);
        if ($namespacePtr === false) {
            return '';
        }

        $endPtr = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $namespacePtr + 1);
        if ($endPtr === false) {
            return '';
        }

        $namespace = '';
        for ($ptr = $namespacePtr + 1; $ptr < $endPtr; $ptr++) {
            if (in_array($tokens[$ptr]['code'], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                $namespace .= $tokens[$ptr]['content'];
            }
        }

        return $this->normalizeClassName($namespace);
    }

    /**
     * @return array<string, string>
     */
    private function collectUseMap(File $phpcsFile): array
    {
        $useMap = [];
        $tokens = $phpcsFile->getTokens();
        $usePtr = 0;

        while (($usePtr = $phpcsFile->findNext(T_USE, $usePtr + 1)) !== false) {
            if (!$this->isTopLevelUse($phpcsFile, $usePtr)) {
                continue;
            }

            $semicolonPtr = $phpcsFile->findNext(T_SEMICOLON, $usePtr + 1);
            if ($semicolonPtr === false) {
                continue;
            }

            $statement = trim($phpcsFile->getTokensAsString($usePtr + 1, $semicolonPtr - $usePtr - 1));
            if ($statement === '' || preg_match('/^(function|const)\s+/i', $statement)) {
                continue;
            }

            if (str_contains($statement, '{')) {
                $this->collectGroupUseMap($statement, $useMap);
                continue;
            }

            $statement = preg_replace('/\s+/', ' ', $statement) ?? $statement;
            $parts = preg_split('/\s+as\s+/i', $statement);
            $className = $this->normalizeClassName($parts[0] ?? '');
            if ($className === '') {
                continue;
            }

            $lastSeparator = strrpos($className, '\\');
            $alias = trim($parts[1] ?? ($lastSeparator === false ? $className : substr($className, $lastSeparator + 1)));
            $useMap[strtolower($alias)] = $className;
        }

        return $useMap;
    }

    /**
     * @return array<int, array{start: int, end: int}>
     */
    private function collectUseStatementRanges(File $phpcsFile): array
    {
        $ranges = [];
        $usePtr = 0;

        while (($usePtr = $phpcsFile->findNext(T_USE, $usePtr + 1)) !== false) {
            if (!$this->isTopLevelUse($phpcsFile, $usePtr)) {
                continue;
            }

            $semicolonPtr = $phpcsFile->findNext(T_SEMICOLON, $usePtr + 1);
            if ($semicolonPtr === false) {
                continue;
            }

            $ranges[] = [
                'start' => $usePtr,
                'end' => $semicolonPtr,
            ];
        }

        return $ranges;
    }

    /**
     * @param array<int, array{start: int, end: int}> $ranges
     */
    private function isInsideUseStatement(int $ptr, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($ptr >= $range['start'] && $ptr <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function isTopLevelUse(File $phpcsFile, int $usePtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        return !isset($tokens[$usePtr]['conditions']) || $tokens[$usePtr]['conditions'] === [];
    }

    /**
     * @param array<string, string> $useMap
     */
    private function collectGroupUseMap(string $statement, array &$useMap): void
    {
        $matches = [];
        $statement = preg_replace('/\s+/', ' ', trim($statement)) ?? $statement;
        if (!preg_match('/^(.+)\\\\\{\s*(.+)\s*}$/', $statement, $matches)) {
            return;
        }

        $prefix = $this->normalizeClassName($matches[1]);
        foreach (explode(',', $matches[2]) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $aliasParts = preg_split('/\s+as\s+/i', $part);
            $classSuffix = $this->normalizeClassName($aliasParts[0] ?? '');
            if ($classSuffix === '') {
                continue;
            }

            $className = $prefix . '\\' . $classSuffix;
            $lastSeparator = strrpos($classSuffix, '\\');
            $alias = trim($aliasParts[1] ?? ($lastSeparator === false ? $classSuffix : substr($classSuffix, $lastSeparator + 1)));
            $useMap[strtolower($alias)] = $className;
        }
    }

    /**
     * @param array<string, string> $useMap
     */
    private function resolveClassName(string $className, string $namespace, array $useMap): string
    {
        $className = $this->normalizeClassName($className);
        if ($className === '') {
            return '';
        }

        $separatorPos = strpos($className, '\\');
        $alias = $separatorPos === false ? $className : substr($className, 0, $separatorPos);
        $mappedClass = $useMap[strtolower($alias)] ?? null;
        if ($mappedClass !== null) {
            return $separatorPos === false ? $mappedClass : $mappedClass . substr($className, $separatorPos);
        }

        if ($separatorPos !== false && $this->hasProjectPrefix($className)) {
            return $className;
        }

        return $namespace === '' ? $className : $namespace . '\\' . $className;
    }

    private function isProjectFactory(string $className): bool
    {
        return str_ends_with($className, 'Factory') && $this->hasProjectPrefix($className);
    }

    private function hasProjectPrefix(string $className): bool
    {
        foreach ($this->projectPrefixes as $prefix) {
            if (str_starts_with($className, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function factoryFileExists(File $phpcsFile, string $factoryClass): bool
    {
        foreach ($this->candidateFactoryPaths($phpcsFile, $factoryClass) as $candidatePath) {
            if (is_file($candidatePath)) {
                return true;
            }
        }

        return false;
    }

    private function expectedFactoryPath(File $phpcsFile, string $factoryClass): string
    {
        $candidatePaths = $this->candidateFactoryPaths($phpcsFile, $factoryClass);

        return $candidatePaths[0] ?? 'app/code/' . str_replace('\\', '/', $factoryClass) . '.php';
    }

    /**
     * @return list<string>
     */
    private function candidateFactoryPaths(File $phpcsFile, string $factoryClass): array
    {
        $rootDir = $this->projectRootDir($phpcsFile->getFilename());
        $paths = [];
        foreach (glob($rootDir . '/packages/*/*/composer.json') ?: [] as $manifest) {
            $metadata = json_decode((string)file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            foreach ($metadata['autoload']['psr-4'] ?? [] as $prefix => $directories) {
                if (str_starts_with($factoryClass, $prefix)) {
                    foreach ((array)$directories as $directory) {
                        $paths[] = dirname($manifest) . '/' . trim($directory, '/') . '/'
                            . str_replace('\\', '/', substr($factoryClass, strlen($prefix))) . '.php';
                    }
                }
            }
        }

        $externalModulePath = $this->externalModuleFactoryPath($factoryClass);
        if ($externalModulePath !== null) {
            $paths[] = $externalModulePath;
        }

        $paths[] = $rootDir . '/app/code/' . str_replace('\\', '/', $factoryClass) . '.php';

        $vendorPath = $this->vendorFactoryPath($rootDir, $factoryClass);
        if ($vendorPath !== null) {
            $paths[] = $vendorPath;
        }

        return array_values(array_unique($paths));
    }

    private function externalModuleFactoryPath(string $factoryClass): ?string
    {
        $moduleName = getenv('VENDIVO_MODULE_NAME');
        $modulePath = getenv('VENDIVO_MODULE_PATH');
        if (!is_string($moduleName) || $moduleName === '' || !is_string($modulePath) || $modulePath === '') {
            return null;
        }

        $moduleNamespace = str_replace('_', '\\', $moduleName) . '\\';
        if (!str_starts_with($factoryClass, $moduleNamespace)) {
            return null;
        }

        $relativeClass = substr($factoryClass, strlen($moduleNamespace));

        return rtrim(str_replace('\\', '/', $modulePath), '/') . '/'
            . str_replace('\\', '/', $relativeClass) . '.php';
    }

    private function vendorFactoryPath(string $rootDir, string $factoryClass): ?string
    {
        $classParts = explode('\\', $factoryClass);
        if (count($classParts) < 3) {
            return null;
        }

        $packageVendor = strtolower($classParts[0]);
        $packageName = 'module-' . $this->kebabCase($classParts[1]);
        $relativeClass = implode('/', array_slice($classParts, 2));

        return $rootDir . '/vendor/' . $packageVendor . '/' . $packageName . '/' . $relativeClass . '.php';
    }

    private function kebabCase(string $value): string
    {
        $value = preg_replace('/(?<!^)([A-Z])/', '-$1', $value) ?? $value;

        return strtolower($value);
    }

    private function projectRootDir(string $filename): string
    {
        $normalized = str_replace('\\', '/', $filename);
        $appCodePos = strpos($normalized, '/app/code/');
        if ($appCodePos !== false) {
            return substr($normalized, 0, $appCodePos);
        }

        $cwd = getcwd();

        return $cwd === false ? dirname($filename) : $cwd;
    }

    private function normalizeClassName(string $className): string
    {
        return trim(trim($className), '\\');
    }
}
