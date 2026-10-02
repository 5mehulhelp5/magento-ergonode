<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
require_once __DIR__ . '/modules.php';

try {
    $configFile = __DIR__ . '/integration/install-config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Configure tools/quality/integration/install-config.php using the .dist template and a separate test database.');
    }
    $install = require $configFile;
    $live = require $root . '/app/etc/env.php';
    $database = $install['db-name'] ?? '';
    if (!preg_match('/^[a-z0-9_]+_test$/D', $database)
        || $database === ($live['db']['connection']['default']['dbname'] ?? '')
        || ($install['db-password'] ?? '') === 'CHANGE_ME'
        || ($install['opensearch-index-prefix'] ?? '') !== $database
    ) {
        throw new RuntimeException('Integration needs a dedicated *_test database, configured credentials and the same isolated OpenSearch index prefix.');
    }
    $runtime = $root . '/var/quality/integration';
    @mkdir($runtime, 0777, true);
    $lock = fopen($runtime . '/run.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another integration test run is active.');
    }
    $arguments = array_slice($argv, 1);
    $selected = $arguments ?: array_values(qualityModules());
    $files = [];
    foreach ($selected as $path) {
        $real = realpath($path);
        if (!$real || !str_starts_with($real, $root . '/packages/')) {
            throw new InvalidArgumentException('Integration scope must be an existing project package path.');
        }
        if (is_file($real)) {
            $candidates = [$real];
        } else {
            $candidates = [];
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) { $candidates[] = $file->getPathname(); }
            }
        }
        foreach ($candidates as $candidate) {
            if (str_contains($candidate, '/Test/Integration/') && str_ends_with($candidate, 'Test.php')) { $files[] = $candidate; }
        }
    }
    if ($files === []) { throw new RuntimeException('No integration tests found in the requested scope.'); }
    sort($files);
    $xml = new DOMDocument();
    $xml->load($root . '/dev/tests/integration/phpunit.xml.dist', LIBXML_NONET);
    $xml->documentElement->setAttribute('bootstrap', $root . '/dev/tests/integration/framework/bootstrap.php');
    $xpath = new DOMXPath($xml);
    foreach (iterator_to_array($xpath->query('/phpunit/testsuites|/phpunit/source|/phpunit/extensions/bootstrap[@class="Qameta\\Allure\\PHPUnit\\AllureExtension"]')) as $node) {
        $node->parentNode->removeChild($node);
    }
    $suite = $xml->createElement('testsuite');
    $suite->setAttribute('name', 'Ergonode Integration');
    foreach ($files as $file) { $suite->appendChild($xml->createElement('file', $file)); }
    $suites = $xml->createElement('testsuites');
    $suites->appendChild($suite);
    $xml->documentElement->appendChild($suites);
    foreach (['TESTS_INSTALL_CONFIG_FILE' => $configFile,
        'TESTS_POST_INSTALL_SETUP_COMMAND_CONFIG_FILE' => $root . '/dev/tests/integration/etc/post-install-setup-command-config.php',
        'TESTS_GLOBAL_CONFIG_FILE' => $root . '/dev/tests/integration/etc/config-global.php',
        'TESTS_GLOBAL_CONFIG_DIR' => $root . '/app/etc'] as $name => $value) {
        $xpath->query('/phpunit/php/const[@name="' . $name . '"]')->item(0)->setAttribute('value', $value);
    }
    $constant = $xml->createElement('const');
    $constant->setAttribute('name', 'TESTS_TEMP_DIR');
    $constant->setAttribute('value', $runtime . '/tmp');
    $xpath->query('/phpunit/php')->item(0)->appendChild($constant);
    $phpunitConfig = $runtime . '/phpunit.xml';
    $xml->save($phpunitConfig);
    $process = proc_open([PHP_BINARY, 'vendor/bin/phpunit', '-c', $phpunitConfig], [STDIN, STDOUT, STDERR], $pipes);
    exit(is_resource($process) ? proc_close($process) : 2);
} catch (Throwable $error) {
    fwrite(STDERR, 'INTEGRATION ERROR: ' . $error->getMessage() . "\n");
    exit(2);
}
