<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Test;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Vendivo\ModuleComposer\Cli\Application;

#[CoversNothing]
final class ApplicationTest extends TestCase
{
    private string $rootDirectory;

    protected function setUp(): void
    {
        $this->rootDirectory = sys_get_temp_dir() . '/vendivo-module-composer-' . bin2hex(random_bytes(6));
        mkdir($this->rootDirectory . '/app/code/Vendivo', 0777, true);
        $this->writeJson($this->rootDirectory . '/composer.json', [
            'name' => 'vendivo/test-project',
            'require' => [],
        ]);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->rootDirectory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rootDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($this->rootDirectory);
    }

    public function testValidModuleProducesOneConciseSuccessLine(): void
    {
        $this->createModule('Example');

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(0, $status);
        self::assertSame("OK [MODULE-COMPOSER] checked=1 errors=0 warnings=0\n", $output);
    }

    public function testProjectFindingsUseStableSingleLineDiagnostics(): void
    {
        $composer = $this->validComposer('Example');
        $composer['type'] = 'library';
        $composer['version'] = '1.0.0';
        $this->createModule('Example', $composer);

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(1, $status);
        self::assertStringContainsString(
            'ERROR [COMPOSER-METADATA] app/code/Vendivo/Example/composer.json:',
            $output
        );
        self::assertStringContainsString(
            'WARNING [COMPOSER-LEGACY] app/code/Vendivo/Example/composer.json:',
            $output
        );
        self::assertStringEndsWith(
            "SUMMARY [MODULE-COMPOSER] checked=1 errors=1 warnings=1\n",
            $output
        );
        self::assertStringNotContainsString("\n\n", $output);
    }

    public function testInvalidJsonIsNormalizedWithoutComposerBoilerplate(): void
    {
        $moduleDirectory = $this->createModule('Broken');
        file_put_contents($moduleDirectory . '/composer.json', '{"name":');

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Broken']);

        self::assertSame(1, $status);
        self::assertStringContainsString(
            'ERROR [COMPOSER-SCHEMA] app/code/Vendivo/Broken/composer.json:',
            $output
        );
        self::assertStringNotContainsString('See https://getcomposer.org', $output);
        self::assertStringEndsWith(
            "SUMMARY [MODULE-COMPOSER] checked=1 errors=1 warnings=0\n",
            $output
        );
    }

    public function testComposerCheckDoesNotValidateSequence(): void
    {
        $composer = $this->validComposer('Example');
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $require['vendivo/module-missing'] = 'dev-main@dev';
        $composer['require'] = $require;
        $this->createModule('Example', $composer, ['Vendivo_Missing']);

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(0, $status);
        self::assertSame("OK [MODULE-COMPOSER] checked=1 errors=0 warnings=0\n", $output);
    }

    public function testMissingPackageUsedByPhpSourceIsReported(): void
    {
        $this->createDependencyPackage(
            'Magento',
            'Catalog',
            'magento/module-catalog',
            'Magento\\Catalog\\',
            ['magento/framework' => '*']
        );
        $moduleDirectory = $this->createModule('Example');
        mkdir($moduleDirectory . '/Model');
        file_put_contents(
            $moduleDirectory . '/Model/Example.php',
            "<?php\nnamespace Vendivo\\Example\\Model;\nuse Magento\\Catalog\\Api\\ProductRepositoryInterface;\n"
        );

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(1, $status);
        self::assertStringContainsString('ERROR [COMPOSER-DEPENDENCY-MISSING]', $output);
        self::assertStringContainsString('magento/module-catalog', $output);
        self::assertStringContainsString(\Magento\Catalog\Api\ProductRepositoryInterface::class, $output);
    }

    public function testMissingPackageUsedByXmlConfigurationIsReported(): void
    {
        $this->createDependencyPackage(
            'Magento',
            'Catalog',
            'magento/module-catalog',
            'Magento\\Catalog\\',
            ['magento/framework' => '*']
        );
        $moduleDirectory = $this->createModule('Example');
        file_put_contents(
            $moduleDirectory . '/etc/di.xml',
            '<config><type name="Magento\\Catalog\\Api\\ProductRepositoryInterface"/></config>'
        );

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(1, $status);
        self::assertStringContainsString('ERROR [COMPOSER-DEPENDENCY-MISSING]', $output);
        self::assertStringContainsString('app/code/Vendivo/Example/etc/di.xml', $output);
    }

    public function testMissingPackageUsedByGraphQlConfigurationIsReported(): void
    {
        $this->createDependencyPackage(
            'Magento',
            'Catalog',
            'magento/module-catalog',
            'Magento\\Catalog\\',
            ['magento/framework' => '*']
        );
        $moduleDirectory = $this->createModule('Example');
        file_put_contents(
            $moduleDirectory . '/etc/schema.graphqls',
            'type Query { product: String @resolver(class: "Magento\\\\Catalog\\\\Model\\\\Resolver") }'
        );

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(1, $status);
        self::assertStringContainsString('ERROR [COMPOSER-DEPENDENCY-MISSING]', $output);
        self::assertStringContainsString('app/code/Vendivo/Example/etc/schema.graphqls', $output);
    }

    public function testTransitivePackageSatisfiesUsedClassDependency(): void
    {
        $this->createMagentoFrameworkPackage();
        $this->createDependencyPackage(
            'Magento',
            'Catalog',
            'magento/module-catalog',
            'Magento\\Catalog\\',
            ['magento/framework' => '*']
        );
        $composer = $this->validComposer('Example');
        $composer['require'] = [
            'php' => '~8.3.0||~8.4.0||~8.5.0',
            'magento/module-catalog' => '*',
        ];
        $moduleDirectory = $this->createModule('Example', $composer);
        mkdir($moduleDirectory . '/Model');
        file_put_contents(
            $moduleDirectory . '/Model/Example.php',
            "<?php\nnamespace Vendivo\\Example\\Model;\nuse Magento\\Framework\\App\\RequestInterface;\n"
        );

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(1, $status);
        self::assertStringContainsString('COMPOSER-DEPENDENCY-MISSING', $output);
        self::assertStringContainsString('not declared directly in require', $output);
    }

    public function testTransitivelyProvidedDirectPackageRemainsAllowed(): void
    {
        $this->createMagentoFrameworkPackage();
        $this->createDependencyPackage(
            'Magento',
            'Catalog',
            'magento/module-catalog',
            'Magento\\Catalog\\',
            ['magento/framework' => '*']
        );
        $composer = $this->validComposer('Example');
        $composer['require']['magento/module-catalog'] = '*';
        $this->createModule('Example', $composer);

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(0, $status);
        self::assertStringNotContainsString('COMPOSER-DEPENDENCY-REDUNDANT', $output);
    }

    public function testTestOnlyClassUsageDoesNotAffectProductionRequires(): void
    {
        $this->createDependencyPackage(
            'Magento',
            'Catalog',
            'magento/module-catalog',
            'Magento\\Catalog\\',
            ['magento/framework' => '*']
        );
        $moduleDirectory = $this->createModule('Example');
        mkdir($moduleDirectory . '/Test/Unit', 0777, true);
        file_put_contents(
            $moduleDirectory . '/Test/Unit/ExampleTest.php',
            "<?php\nuse Magento\\Catalog\\Api\\ProductRepositoryInterface;\n"
        );

        [$status, $output] = $this->runApplication(['check', '--module=Vendivo_Example']);

        self::assertSame(0, $status);
        self::assertSame("OK [MODULE-COMPOSER] checked=1 errors=0 warnings=0\n", $output);
    }

    public function testGenerateCreatesMetadataThatPassesCheck(): void
    {
        $this->createModule('Generated', null);

        [$generateStatus, $generateOutput] = $this->runApplication([
            'generate',
            '--module=Vendivo_Generated',
            '--description=Generated fixture.',
        ]);
        [$checkStatus, $checkOutput] = $this->runApplication(['check', '--module=Vendivo_Generated']);

        self::assertSame(0, $generateStatus);
        self::assertSame(
            "WROTE [MODULE-COMPOSER-GENERATE] app/code/Vendivo/Generated/composer.json\n",
            $generateOutput
        );
        self::assertSame(0, $checkStatus);
        self::assertSame("OK [MODULE-COMPOSER] checked=1 errors=0 warnings=0\n", $checkOutput);
    }

    public function testGenerateAddsUsedPackageAndPreservesExplicitDependencies(): void
    {
        $this->createMagentoFrameworkPackage();
        $this->createDependencyPackage(
            'Magento',
            'Catalog',
            'magento/module-catalog',
            'Magento\\Catalog\\',
            ['magento/framework' => '*']
        );
        $composer = $this->validComposer('Generated');
        $moduleDirectory = $this->createModule('Generated', $composer);
        mkdir($moduleDirectory . '/Model');
        file_put_contents(
            $moduleDirectory . '/Model/Generated.php',
            "<?php\nnamespace Vendivo\\Generated\\Model;\nuse Magento\\Catalog\\Api\\ProductRepositoryInterface;\n"
        );

        [$generateStatus] = $this->runApplication([
            'generate',
            '--module=Vendivo_Generated',
            '--force',
        ]);
        $generated = json_decode(
            (string)file_get_contents($moduleDirectory . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(0, $generateStatus);
        self::assertSame('*', $generated['require']['magento/module-catalog'] ?? null);
        self::assertSame('*', $generated['require']['magento/framework']);
    }

    public function testCliFailureUsesExitTwoAndNormalizedOutput(): void
    {
        [$status, $output] = $this->runApplication(['check', '--module=invalid']);

        self::assertSame(2, $status);
        self::assertStringStartsWith('ERROR [MODULE-COMPOSER-CLI] -:', $output);
        self::assertStringEndsWith(
            "SUMMARY [MODULE-COMPOSER] checked=0 errors=1 warnings=0\n",
            $output
        );
    }

    /**
     * @param array<string, mixed>|null $composer
     * @param list<string> $sequence
     */
    private function createModule(string $name, ?array $composer = [], array $sequence = []): string
    {
        $moduleDirectory = $this->rootDirectory . '/app/code/Vendivo/' . $name;
        mkdir($moduleDirectory . '/etc', 0777, true);
        $sequenceXml = '';
        if ($sequence !== []) {
            $items = implode('', array_map(
                static fn (string $module): string => sprintf('<module name="%s"/>', $module),
                $sequence
            ));
            $sequenceXml = '<sequence>' . $items . '</sequence>';
        }
        file_put_contents(
            $moduleDirectory . '/etc/module.xml',
            sprintf('<config><module name="Vendivo_%s">%s</module></config>', $name, $sequenceXml)
        );
        file_put_contents($moduleDirectory . '/registration.php', "<?php\n");
        if ($composer !== null) {
            $this->writeJson(
                $moduleDirectory . '/composer.json',
                $composer === [] ? $this->validComposer($name) : $composer
            );
        }

        return $moduleDirectory;
    }

    /** @return array<string, mixed> */
    private function validComposer(string $module): array
    {
        return [
            'name' => 'vendivo/module-' . strtolower($module),
            'description' => 'Fixture module.',
            'type' => 'magento2-module',
            'license' => 'proprietary',
            'authors' => [
                [
                    'name' => 'Pack Hauer',
                    'email' => 'packhauer@gmail.com',
                ],
            ],
            'require' => [
                'php' => '~8.3.0||~8.4.0||~8.5.0',
                'magento/framework' => '*',
            ],
            'autoload' => [
                'files' => ['registration.php'],
                'psr-4' => [sprintf('Vendivo\\%s\\', $module) => ''],
            ],
            'extra' => [
                'vendivo' => ['lifecycle' => 'development'],
            ],
        ];
    }

    private function createMagentoFrameworkPackage(): void
    {
        $this->createDependencyPackage(
            'Magento',
            'Framework',
            'magento/framework',
            'Magento\\Framework\\'
        );
    }

    /** @param array<string, string> $require */
    private function createDependencyPackage(
        string $vendor,
        string $module,
        string $package,
        string $namespace,
        array $require = []
    ): void {
        $directory = sprintf('%s/app/code/%s/%s', $this->rootDirectory, $vendor, $module);
        mkdir($directory, 0777, true);
        $this->writeJson($directory . '/composer.json', [
            'name' => $package,
            'require' => $require,
            'autoload' => ['psr-4' => [$namespace => '']],
        ]);
    }

    /**
     * @param list<string> $arguments
     * @return array{int, string}
     */
    private function runApplication(array $arguments): array
    {
        ob_start();
        try {
            $status = (new Application($this->rootDirectory))->run($arguments);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertIsString($output);

        return [$status, $output];
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );
    }
}
