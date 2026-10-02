<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
require $root . '/vendor/autoload.php';
require_once __DIR__ . '/modules.php';

/** @param list<string> $command */
function execute(array $command, ?string $log = null): int
{
    $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
    if ($log !== null) {
        $descriptors[1] = ['file', $log, 'w'];
        $descriptors[2] = ['file', $log, 'a'];
    }
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ' . $command[0]);
    }
    return proc_close($process);
}

/** @return list<string> */
function testFiles(string $path, string $kind): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $name = $file->getPathname();
        if ($file->isFile() && ($kind === 'js'
            ? preg_match('~/Test/Js/.*\.test\.(cjs|mjs|js)$~', $name)
            : str_contains($name, '/Test/' . $kind . '/') && str_ends_with($name, 'Test.php'))) {
            $files[] = $name;
        }
    }
    sort($files);
    return $files;
}

/** @param list<string> $arguments */
function run(string $command, array $arguments): int
{
    $modules = qualityModules();
    $paths = ['packages/ergonode', 'packages/packhauer'];
    switch ($command) {
        case 'modules':
            foreach ($modules as $name => $path) { echo "$name $path\n"; }
            echo 'Modules: ' . count($modules) . "\n";
            return 0;
        case 'module-path':
            $module = $arguments[0] ?? '';
            if (!isset($modules[$module])) { throw new InvalidArgumentException('Unknown module: ' . $module); }
            echo $modules[$module] . "\n";
            return 0;
        case 'all':
            @mkdir('var/quality/full', 0777, true);
            $failed = [];
            foreach (['composer', 'xml-format', 'xml-schema', 'area', 'phpcs', 'phpmd', 'phpstan', 'arkitect', 'unit', 'js'] as $gate) {
                $log = "var/quality/full/$gate.log";
                $status = execute([PHP_BINARY, __FILE__, $gate], $log);
                printf("%s: %s (exit %d); %s\n", $gate, $status === 0 ? 'PASS' : 'FAIL', $status, $log);
                if ($status !== 0) { $failed[] = $gate; }
            }
            echo $failed === [] ? "SUMMARY: PASS\n" : 'SUMMARY: FAIL ' . implode(', ', $failed) . "\n";
            return $failed === [] ? 0 : 1;
        case 'gate':
            [$gate, $module, $path] = array_slice($arguments, 0, 3);
            if (($modules[$module] ?? null) !== $path) { throw new InvalidArgumentException('Module/path mismatch.'); }
            $extra = array_slice($arguments, 3);
            return match ($gate) {
                'composer', 'area', 'xml-format', 'xml-schema' => run($gate, [$module]),
                'phpstan', 'arkitect' => run($gate, [$path]),
                'phpcs' => run('phpcs', $extra[0] === 'files' ? ['--file-list=' . $extra[1]] : [$path]),
                'phpmd' => run('phpmd', $extra[0] === 'files' ? ['--input-file=' . $extra[1]] : [$path]),
                'unit', 'js', 'integration' => run($gate, [$extra[0]]),
                'schema-whitelist' => execute(['bash', 'tools/quality/db-schema-whitelist.sh', 'check', $module, $path, '--', PHP_BINARY, 'bin/magento']),
                default => throw new InvalidArgumentException('Unknown gate: ' . $gate),
            };
        case 'composer':
            return execute(array_merge([PHP_BINARY, 'vendor/bin/vendivo-module-composer', 'check'],
                $arguments === [] ? [] : ['--module=' . $arguments[0]]));
        case 'xml-schema':
        case 'xml-format':
        case 'area':
            if ($arguments !== []) {
                $module = $arguments[0];
                if (!isset($modules[$module])) { throw new InvalidArgumentException('Unknown module: ' . $module); }
                $modules = [$module => $modules[$module]];
            } elseif ($command !== 'area') {
                return $command === 'xml-schema'
                    ? execute([PHP_BINARY, 'tools/quality/validate-magento-xml.php', '--path=packages'])
                    : execute(['bash', 'tools/quality/xml-format.sh', 'check', 'packages']);
            }
            $failed = false;
            foreach ($modules as $module => $path) {
                if ($command === 'area') {
                    putenv('AREA_MODULES_MODULE=' . $module);
                    putenv('AREA_MODULES_PATH=' . $path);
                    $status = execute(['bash', 'tools/quality/check-area-modules.sh']);
                } elseif ($command === 'xml-schema') {
                    $status = execute([PHP_BINARY, 'tools/quality/validate-magento-xml.php', '--path=' . $path]);
                } else {
                    $status = execute(['bash', 'tools/quality/xml-format.sh', 'check', $path]);
                }
                $failed = $status !== 0 || $failed;
            }
            return $failed ? 1 : 0;
        case 'phpcs':
            return execute(array_merge([PHP_BINARY, 'vendor/bin/phpcs', '--standard=phpcs.xml.dist', '--extensions=php,phtml', '-s'], $arguments));
        case 'phpmd':
            return execute(array_merge([PHP_BINARY, 'vendor/bin/phpmd', 'analyze'],
                $arguments ?: $paths,
                ['--format=text', '--ruleset=phpmd.xml.dist', '--exclude=*/Test/*', '--suffixes=php', '--no-progress']));
        case 'phpstan':
        case 'dead-code':
            return execute(array_merge([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=2G',
                '--configuration=' . ($command === 'phpstan' ? 'phpstan.neon.dist' : 'phpstan-dead-code.neon.dist')], $arguments));
        case 'arkitect':
            putenv('ARKITECT_PATHS=' . implode(',', $arguments));
            return execute([PHP_BINARY, 'vendor/bin/phparkitect', 'check', '--config=phparkitect.php']);
        case 'unit':
            return execute(array_merge([PHP_BINARY, 'vendor/bin/phpunit', '-c', 'phpunit.xml.dist'], $arguments));
        case 'js':
            $files = [];
            foreach ($arguments ?: $paths as $path) { array_push($files, ...testFiles($path, 'js')); }
            if ($files === []) { echo "SKIP no JavaScript tests in selected scope\n"; return 0; }
            return execute(array_merge(['node', '--test'], $files));
        case 'integration':
            return execute(array_merge([PHP_BINARY, 'tools/quality/integration.php'], $arguments));
        case 'duplicates':
            return (new Vendivo\CodeDuplicates\ConsoleApplication(getcwd(), $paths))->run($arguments);
        case 'infection':
            $path = array_shift($arguments);
            if (!$path || !is_dir($path) || !str_starts_with($path, 'packages/')) {
                throw new InvalidArgumentException('Infection requires a package source directory.');
            }
            if (!extension_loaded('xdebug') && !extension_loaded('pcov')) {
                throw new RuntimeException('Infection requires Xdebug or PCOV. Enable a coverage driver for this optional audit.');
            }
            @mkdir('var/infection', 0777, true);
            $lock = fopen('var/infection/run.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Another Infection audit is running.'); }
            return execute(array_merge([PHP_BINARY, 'vendor/bin/infection', '--configuration=infection.json5.dist', '--filter=' . $path], $arguments));
        case 'tooling-tests':
            $commands = [
                [PHP_BINARY, 'vendor/bin/phpunit', '-c', 'tools/packages/vendivo-module-composer/phpunit.xml.dist'],
                [PHP_BINARY, 'vendor/bin/phpunit', '-c', 'tools/packages/vendivo-code-duplicates/phpunit.xml.dist'],
                ['bash', 'tools/packages/vendivo-module-quality/tests/run.sh'],
                [PHP_BINARY, 'vendor/bin/phpunit', '--no-configuration', '--bootstrap=tests/bootstrap-unit.php', 'tools/quality/tests'],
            ];
            $failed = false;
            foreach ($commands as $test) { $failed = execute($test) !== 0 || $failed; }
            return $failed ? 1 : 0;
        default:
            throw new InvalidArgumentException('Unknown quality command: ' . $command);
    }
}

try {
    exit(run($argv[1] ?? 'modules', array_slice($argv, 2)));
} catch (Throwable $error) {
    fwrite(STDERR, 'QUALITY ERROR: ' . $error->getMessage() . "\n");
    exit(2);
}
