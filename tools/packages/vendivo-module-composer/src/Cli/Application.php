<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Cli;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Vendivo\ModuleComposer\Dependency\ComposerDependencyInspector;
use Vendivo\ModuleComposer\Diagnostic;
use Vendivo\ModuleComposer\Generation\ComposerGenerator;
use Vendivo\ModuleComposer\ModuleCatalog;
use Vendivo\ModuleComposer\Reporting\TextReporter;
use Vendivo\ModuleComposer\Validation\ComposerSchemaValidator;
use Vendivo\ModuleComposer\Validation\ModuleMetadataValidator;
use Vendivo\ModuleComposer\ValidationReport;

final class Application
{
    private readonly ModuleCatalog $moduleCatalog;

    private readonly ModuleMetadataValidator $validator;

    private readonly ComposerGenerator $generator;

    private readonly TextReporter $reporter;

    public function __construct(private readonly ?string $rootDirectory = null)
    {
        $this->moduleCatalog = new ModuleCatalog();
        $dependencyInspector = new ComposerDependencyInspector();
        $this->validator = new ModuleMetadataValidator(
            $this->moduleCatalog,
            new ComposerSchemaValidator(),
            $dependencyInspector
        );
        $this->generator = new ComposerGenerator($this->moduleCatalog, $dependencyInspector);
        $this->reporter = new TextReporter();
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        $command = array_shift($arguments);
        if ($command === null || in_array($command, ['help', '--help', '-h'], true)) {
            $this->usage();

            return $command === null ? 2 : 0;
        }

        try {
            $options = $this->parseOptions($arguments);
            $rootDirectory = $this->resolveRootDirectory();

            return match ($command) {
                'check' => $this->check($rootDirectory, $options),
                'generate' => $this->generate($rootDirectory, $options),
                default => throw new InvalidArgumentException(sprintf('Unknown command: %s', $command)),
            };
        } catch (Throwable $exception) {
            $report = new ValidationReport();
            $report->add(Diagnostic::error('MODULE-COMPOSER-CLI', '-', $exception->getMessage()));
            echo $this->reporter->render($report);

            return 2;
        }
    }

    /**
     * @param array<string, string> $options
     */
    private function check(string $rootDirectory, array $options): int
    {
        $moduleName = $options['module'] ?? null;
        $modulePath = $options['path'] ?? null;
        $modules = $this->moduleCatalog->select($rootDirectory, $moduleName, $modulePath);
        $report = new ValidationReport();
        foreach ($modules as $moduleDirectory) {
            $report->incrementCheckedModules();
            $report->addAll($this->validator->validate(
                $rootDirectory,
                $moduleDirectory,
                $modulePath !== null ? $moduleName : null
            ));
        }

        echo $this->reporter->render($report);

        return $report->hasErrors() ? 1 : 0;
    }

    /**
     * @param array<string, string> $options
     */
    private function generate(string $rootDirectory, array $options): int
    {
        if (isset($options['path'])) {
            throw new InvalidArgumentException('The --path option is supported only by check.');
        }

        $moduleName = $options['module'] ?? null;
        $modules = $this->moduleCatalog->select($rootDirectory, $moduleName, null);
        foreach ($modules as $moduleDirectory) {
            echo $this->generator->generate(
                $rootDirectory,
                $moduleDirectory,
                $options['description'] ?? null,
                $options['family'] ?? null,
                $this->isTruthy($options['force'] ?? null),
                $this->isTruthy($options['dry-run'] ?? null)
            );
        }

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @return array<string, string>
     */
    private function parseOptions(array $arguments): array
    {
        $options = [];
        foreach ($arguments as $argument) {
            $argument = str_starts_with($argument, '--') ? substr($argument, 2) : $argument;
            if ($argument === 'force' || $argument === 'dry-run') {
                $options[$argument] = '1';
                continue;
            }
            if (!str_contains($argument, '=')) {
                throw new InvalidArgumentException(sprintf('Invalid option: %s', $argument));
            }

            [$key, $value] = explode('=', $argument, 2);
            $options[$key] = $value;
        }

        return $options;
    }

    private function resolveRootDirectory(): string
    {
        $configuredRoot = $this->rootDirectory;
        if ($configuredRoot === null || $configuredRoot === '') {
            $environmentRoot = getenv('MODULE_COMPOSER_ROOT');
            $configuredRoot = is_string($environmentRoot) && $environmentRoot !== ''
                ? $environmentRoot
                : getcwd();
        }
        if (!is_string($configuredRoot) || !is_dir($configuredRoot)) {
            throw new RuntimeException('Unable to resolve the project root directory.');
        }

        return realpath($configuredRoot) ?: $configuredRoot;
    }

    private function isTruthy(?string $value): bool
    {
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'dry-run'], true);
    }

    private function usage(): void
    {
        fwrite(
            STDERR,
            "Usage:\n" .
            "  vendivo-module-composer check [--module=Vendor_Module] [--path=path/to/module]\n" .
            "  vendivo-module-composer generate [--module=Vendor_Module] [--description='...'] " .
            "[--family=Vendor_Feature] [--force] [--dry-run]\n"
        );
    }
}
