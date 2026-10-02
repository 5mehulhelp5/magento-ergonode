<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vendivo\ModuleComposer\ModuleCatalog;

require_once dirname(__DIR__) . '/modules.php';

final class QualityContractsTest extends TestCase
{
    public function testCatalogFindsEveryPackageAndResolvesItsMagentoName(): void
    {
        $root = dirname(__DIR__, 3);
        $catalog = new ModuleCatalog();
        $modules = qualityModules();
        self::assertNotEmpty($modules);
        self::assertCount(count(glob($root . '/packages/*/*/etc/module.xml')), $modules);
        self::assertCount(count($modules), $catalog->select($root, null, null));
        foreach ($modules as $module => $path) {
            self::assertSame([$root . '/' . $path], $catalog->select($root, $module, null));
            self::assertSame($module, $catalog->moduleName($root . '/' . $path));
        }
    }

    public function testExplicitFactoryIsCheckedInPackageSourcesInsteadOfGenerated(): void
    {
        $root = sys_get_temp_dir() . '/quality-factory-' . bin2hex(random_bytes(6));
        $module = $root . '/packages/ergonode/module-example';
        mkdir($module . '/Model', 0777, true);
        file_put_contents($module . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Ergonode\\Example\\' => '']]]));
        $source = $module . '/Model/Service.php';
        file_put_contents($source, '<?php namespace Ergonode\\Example\\Model; class Service { public function __construct(ItemFactory $factory) {} }');
        mkdir($root . '/generated/code/Ergonode/Example/Model', 0777, true);
        file_put_contents($root . '/generated/code/Ergonode/Example/Model/ItemFactory.php', '<?php');
        try {
            [$status, $report] = $this->sniff('GeneratedFactory/MissingExplicitFactory', $source, $root);
            self::assertNotSame(0, $status, $report);
            self::assertStringContainsString('MissingExplicitFactory', $report);
            file_put_contents($module . '/Model/ItemFactory.php', '<?php namespace Ergonode\\Example\\Model; class ItemFactory {}');
            [$status, $report] = $this->sniff('GeneratedFactory/MissingExplicitFactory', $source, $root);
            self::assertSame(0, $status, $report);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testAroundMustContinueTheChain(): void
    {
        $root = sys_get_temp_dir() . '/quality-plugin-' . bin2hex(random_bytes(6));
        mkdir($root);
        $source = $root . '/Plugin.php';
        try {
            file_put_contents($source, '<?php class Plugin { public function aroundSave($subject, callable $proceed) { return null; } }');
            [$status, $report] = $this->sniff('Plugins/AroundPluginCallsProceed', $source, $root);
            self::assertNotSame(0, $status, $report);
            self::assertStringContainsString('MissingProceedCall', $report);
            file_put_contents($source, '<?php class Plugin { public function aroundSave($subject, callable $proceed) { return $proceed(); } }');
            [$status, $report] = $this->sniff('Plugins/AroundPluginCallsProceed', $source, $root);
            self::assertSame(0, $status, $report);
        } finally {
            $this->removeDirectory($root);
        }
    }


    public function testSimpleAroundWrappersAreRejectedButTryFinallyIsAllowed(): void
    {
        $root = sys_get_temp_dir() . '/quality-around-' . bin2hex(random_bytes(6));
        mkdir($root);
        $source = $root . '/Plugin.php';
        try {
            foreach ([
                'return $proceed();',
                '$this->prepare(); return $proceed();',
                '$result = $proceed(); $this->log($result); return $result;',
            ] as $body) {
                file_put_contents($source, '<?php class Plugin { public function aroundSave($subject, callable $proceed) {' . $body . '} }');
                [$status, $report] = $this->sniff('Plugins/PreferBeforeAfter', $source, $root);
                self::assertNotSame(0, $status, $report);
                self::assertStringContainsString('UnnecessaryAround', $report);
            }
            file_put_contents($source, '<?php class Plugin { public function aroundSave($subject, callable $proceed) { try { return $proceed(); } finally { $this->release(); } } }');
            [$status, $report] = $this->sniff('Plugins/PreferBeforeAfter', $source, $root);
            self::assertSame(0, $status, $report);
            file_put_contents($source, '<?php class Plugin { public function aroundSave($subject, callable $proceed, $value) { $result = $proceed($value + 1); $this->log($result); return $result; } }');
            [$status, $report] = $this->sniff('Plugins/PreferBeforeAfter', $source, $root);
            self::assertSame(0, $status, $report);
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @return array{int, string} */
    private function sniff(string $name, string $source, string $cwd): array
    {
        $project = dirname(__DIR__, 3);
        $ruleset = $cwd . '/ruleset.xml';
        file_put_contents($ruleset, '<ruleset name="Fixture"><rule ref="' . $project . '/tools/quality/phpcs/Vendivo/Sniffs/' . $name . 'Sniff.php"/></ruleset>');
        $command = [PHP_BINARY, $project . '/vendor/bin/phpcs', '-s', '--standard=' . $ruleset, $source];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $cwd);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        return [proc_close($process), $output];
    }

    private function removeDirectory(string $root): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($root);
    }
}
