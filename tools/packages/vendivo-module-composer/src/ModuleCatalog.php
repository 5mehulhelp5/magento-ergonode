<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer;

use InvalidArgumentException;

final class ModuleCatalog
{
    /**
     * @return list<string>
     */
    public function select(string $rootDirectory, ?string $moduleName, ?string $modulePath): array
    {
        if ($modulePath !== null && $modulePath !== '') {
            if ($moduleName === null || !$this->isValidModuleName($moduleName)) {
                throw new InvalidArgumentException('A valid --module=Vendor_Module is required with --path.');
            }

            $directory = str_starts_with($modulePath, '/')
                ? $modulePath
                : $rootDirectory . '/' . $modulePath;
            $directory = realpath($directory) ?: $directory;
            if (!is_dir($directory)) {
                throw new InvalidArgumentException(sprintf('Module path does not exist: %s', $modulePath));
            }

            return [$directory];
        }

        if ($moduleName !== null && $moduleName !== '') {
            if (!$this->isValidModuleName($moduleName)) {
                throw new InvalidArgumentException(sprintf('Invalid module name: %s', $moduleName));
            }

            $directory = $this->moduleDirectory($rootDirectory, $moduleName);
            if (!is_dir($directory)) {
                throw new InvalidArgumentException(sprintf(
                    'Module does not exist: %s',
                    $this->relativePath($rootDirectory, $directory)
                ));
            }

            return [$directory];
        }

        $modules = [];
        foreach (['Vendivo', 'PackHauer', 'Ergonode'] as $vendor) {
            $vendorDirectory = $rootDirectory . '/app/code/' . $vendor;
            if (!is_dir($vendorDirectory)) {
                continue;
            }

            foreach (scandir($vendorDirectory) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $moduleDirectory = $vendorDirectory . '/' . $item;
                if ($this->isModuleDirectory($moduleDirectory)) {
                    $modules[] = $moduleDirectory;
                }
            }
        }

        foreach (glob($rootDirectory . '/packages/*/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if ($this->isModuleDirectory($directory)) {
                $modules[] = $directory;
            }
        }
        sort($modules);
        if ($modules === []) {
            throw new InvalidArgumentException('No modules found.');
        }

        return $modules;
    }

    public function moduleDirectory(string $rootDirectory, string $moduleName): string
    {
        [$vendor, $module] = explode('_', $moduleName, 2);

        foreach (glob($rootDirectory . '/packages/*/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if ($this->moduleName($directory) === $moduleName) {
                return $directory;
            }
        }
        return $rootDirectory . '/app/code/' . $vendor . '/' . $module;
    }

    public function moduleName(string $moduleDirectory): string
    {
        $file = $moduleDirectory . '/etc/module.xml';
        if (is_file($file)) {
            $xml = simplexml_load_file($file, options: LIBXML_NONET);
            $name = (string)($xml->module['name'] ?? $xml['name'] ?? '');
            if ($this->isValidModuleName($name)) {
                return $name;
            }
        }
        return basename(dirname($moduleDirectory)) . '_' . basename($moduleDirectory);
    }

    public function packageName(string $moduleName): string
    {
        if (!str_contains($moduleName, '_')) {
            return '';
        }

        [$vendor, $module] = explode('_', $moduleName, 2);
        $vendor = strtolower($vendor);
        if ($vendor === 'magento' && $module === 'Framework') {
            return 'magento/framework';
        }

        return $vendor . '/module-' . $this->packageModuleSegment($module);
    }

    public function moduleVendor(string $moduleName): string
    {
        return strtolower((string)strstr($moduleName, '_', true));
    }

    public function relativePath(string $rootDirectory, string $path): string
    {
        return str_starts_with($path, $rootDirectory . '/')
            ? substr($path, strlen($rootDirectory) + 1)
            : $path;
    }

    private function isValidModuleName(string $moduleName): bool
    {
        return preg_match('/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/', $moduleName) === 1;
    }

    private function isModuleDirectory(string $moduleDirectory): bool
    {
        return is_dir($moduleDirectory)
            && (is_file($moduleDirectory . '/registration.php')
                || is_file($moduleDirectory . '/etc/module.xml'));
    }

    private function packageModuleSegment(string $module): string
    {
        $module = str_replace(
            ['GraphQl', 'AdminUi', 'WebApi', 'Webapi', 'UrlRewrite'],
            ['Graph Ql', 'Admin Ui', 'Webapi', 'Webapi', 'Url Rewrite'],
            $module
        );

        $segments = preg_split('/\s+/', trim($module)) ?: [$module];
        $words = [];
        foreach ($segments as $segment) {
            foreach (preg_split('/(?=[A-Z])/', $segment, -1, PREG_SPLIT_NO_EMPTY) ?: [$segment] as $word) {
                $words[] = strtolower($word);
            }
        }

        return implode('-', $words);
    }
}
