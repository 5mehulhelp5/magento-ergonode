<?php

declare(strict_types=1);

namespace Vendivo\PHPStan\Autoload;

use bitExpert\PHPStan\Magento\Autoload\DataProvider\ClassLoaderProvider;

final class SourceClassLoaderProvider extends ClassLoaderProvider
{
    private readonly string $sourceRoot;

    public function __construct(string $magentoRoot)
    {
        $this->sourceRoot = rtrim($magentoRoot, '/\\');
        parent::__construct($magentoRoot);
    }

    public function exists(string $className): bool
    {
        return $this->findFile($className) !== false;
    }

    /**
     * @param mixed $className
     * @return string|false
     */
    public function findFile($className)
    {
        if (is_string($className)) {
            $sourceFile = $this->findSourceFile($className);
            if ($sourceFile !== null) {
                return $sourceFile;
            }
        }

        return parent::findFile($className);
    }

    private function findSourceFile(string $className): ?string
    {
        $className = ltrim($className, '\\');
        if ($className === '' || str_contains($className, "\0")) {
            return null;
        }

        $relativePath = str_replace('\\', '/', $className) . '.php';
        $sourceFile = $this->sourceRoot . '/app/code/' . $relativePath;

        return is_file($sourceFile) ? $sourceFile : null;
    }
}
