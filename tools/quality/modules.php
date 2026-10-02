<?php

declare(strict_types=1);

/** @return array<string, string> Module name => repository-relative path. */
function qualityModules(): array
{
    $root = dirname(__DIR__, 2);
    $modules = [];
    foreach (glob($root . '/packages/*/*/etc/module.xml') ?: [] as $file) {
        $xml = simplexml_load_file($file, options: LIBXML_NONET);
        $name = (string)($xml->module['name'] ?? '');
        if (!preg_match('/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/D', $name)) {
            throw new RuntimeException('Invalid module declaration: ' . $file);
        }
        if (isset($modules[$name])) {
            throw new RuntimeException('Duplicate module: ' . $name);
        }
        $modules[$name] = substr(dirname($file, 2), strlen($root) + 1);
    }
    if ($modules === []) {
        throw new RuntimeException('No project modules found in packages/*/*.');
    }
    ksort($modules);
    return $modules;
}
