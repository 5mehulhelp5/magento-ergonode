<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Magento\Framework\Config\Dom as MagentoDom;
use Magento\FunctionalTestingFramework\Config\Dom as MftfDom;

/**
 * @param list<string> $arguments
 * @return array{module: ?string, path: ?string}
 */
function parseOptions(array $arguments): array
{
    $options = ['module' => null, 'path' => null];

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--module=')) {
            if ($options['module'] !== null) {
                throw new InvalidArgumentException('The --module option may only be provided once.');
            }
            $options['module'] = substr($argument, strlen('--module='));
            continue;
        }
        if (str_starts_with($argument, '--path=')) {
            if ($options['path'] !== null) {
                throw new InvalidArgumentException('The --path option may only be provided once.');
            }
            $options['path'] = substr($argument, strlen('--path='));
            continue;
        }

        throw new InvalidArgumentException("Unknown argument: {$argument}");
    }

    if ($options['module'] !== null && $options['path'] !== null) {
        throw new InvalidArgumentException('Use only one of --module or --path.');
    }
    if ($options['module'] !== null
        && preg_match('/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/', $options['module']) !== 1
    ) {
        throw new InvalidArgumentException("Invalid module name: {$options['module']}");
    }
    if ($options['path'] === '') {
        throw new InvalidArgumentException('The --path option requires a value.');
    }

    return $options;
}

/**
 * @return list<string>
 */
function discoverXmlFiles(string $discoveryRoot, ?string $module, ?string $path): array
{
    $explicitScope = $module !== null || $path !== null;
    if ($module !== null) {
        $scopes = [normalizeExistingPath(
            $discoveryRoot . '/app/code/' . str_replace('_', '/', $module)
        )];
    } elseif ($path !== null) {
        $scopes = [normalizeExistingPath($path)];
    } else {
        $scopes = [];
        foreach (['Vendivo', 'PackHauer'] as $vendor) {
            $scope = $discoveryRoot . '/app/code/' . $vendor;
            if (file_exists($scope)) {
                $scopes[] = normalizeExistingPath($scope);
            }
        }
    }

    $files = [];
    foreach ($scopes as $scope) {
        if (is_file($scope)) {
            if (str_ends_with($scope, '.xml')) {
                $files[] = $scope;
            }
        } else {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($scope, FilesystemIterator::SKIP_DOTS)
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.xml')) {
                    $files[] = $file->getRealPath();
                }
            }
        }
    }

    sort($files, SORT_STRING);
    if ($explicitScope && $files === []) {
        throw new InvalidArgumentException('No XML files found in the explicit scope.');
    }

    return $files;
}

function normalizeExistingPath(string $path): string
{
    $normalized = realpath($path);
    if ($normalized === false) {
        throw new InvalidArgumentException("Path does not exist: {$path}");
    }

    return $normalized;
}

function resolveSchema(string $schema): string
{
    if (preg_match('/^urn:magento:(framework(?:-[a-z0-9-]+)?|module|setup):/', $schema) === 1) {
        return $schema;
    }
    if (str_starts_with($schema, 'urn:magento:mftf:')) {
        $package = InstalledVersions::getInstallPath(
            'magento/magento2-functional-testing-framework'
        );
        if ($package === null) {
            throw new RuntimeException('MFTF package is not installed.');
        }
        $prefixes = [
            'urn:magento:mftf:Page/' => 'Page/',
            'urn:magento:mftf:Test/' => 'Test/',
            'urn:magento:mftf:Suite/' => 'Suite/',
            'urn:magento:mftf:DataGenerator/' => 'DataGenerator/',
        ];
        foreach ($prefixes as $urnPrefix => $relativePrefix) {
            if (str_starts_with($schema, $urnPrefix)) {
                $path = $package . '/src/Magento/FunctionalTestingFramework/'
                    . $relativePrefix . substr($schema, strlen($urnPrefix));
                if (!is_file($path)) {
                    throw new RuntimeException("MFTF schema does not exist: {$schema}");
                }
                return $path;
            }
        }
    }
    throw new DomainException("Unsupported schema URN: {$schema}");
}

function resolveValidationSchema(string $schema, string $resolvedSchema): string
{
    $runtimeSchemas = [
        'urn:magento:mftf:Page/etc/PageObject.xsd' => 'mergedPageObject.xsd',
        'urn:magento:mftf:Page/etc/SectionObject.xsd' => 'mergedSectionObject.xsd',
        'urn:magento:mftf:Test/etc/testSchema.xsd' => 'mergedTestSchema.xsd',
        'urn:magento:mftf:Test/etc/actionGroupSchema.xsd' => 'mergedActionGroupSchema.xsd',
    ];
    $runtimeSchema = $runtimeSchemas[$schema] ?? null;
    if ($runtimeSchema === null) {
        return $resolvedSchema;
    }

    $runtimeSchemaPath = dirname($resolvedSchema) . '/' . $runtimeSchema;
    if (!is_file($runtimeSchemaPath)) {
        throw new RuntimeException("MFTF runtime schema does not exist: {$runtimeSchemaPath}");
    }

    return $runtimeSchemaPath;
}

function printFinding(string $path, int $line, string $category, string $message, string $schema): void
{
    printf(
        "%s:%d [%s]\n%s\nSchema: %s\n",
        $path,
        $line,
        $category,
        trim($message),
        $schema === '' ? '(missing)' : $schema
    );
}

/**
 * @return array{line: int, message: string}
 */
function normalizeSchemaError(string $error): array
{
    $line = 1;
    if (preg_match('/\nLine: ([0-9]+)/', $error, $matches) === 1) {
        $line = (int) $matches[1];
    }

    $message = preg_replace('/\nLine: [0-9]+\n.*$/s', '', $error) ?? $error;

    return ['line' => $line, 'message' => $message];
}

/**
 * @param list<string> $files
 * @return array{files: int, errors: int}
 */
function validateFiles(array $files): array
{
    $failedFiles = 0;
    $errorCount = 0;

    foreach ($files as $file) {
        $fileFailed = false;
        $dom = new DOMDocument();
        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $dom->load($file, LIBXML_NONET);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);

        if (!$loaded) {
            $firstError = $xmlErrors[0] ?? null;
            printFinding(
                $file,
                $firstError?->line ?? 1,
                'XML',
                'Malformed XML: ' . ($firstError === null ? 'Unable to parse document.' : trim($firstError->message)),
                ''
            );
            $failedFiles++;
            $errorCount++;
            continue;
        }

        $schema = $dom->documentElement?->getAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'noNamespaceSchemaLocation'
        ) ?? '';
        if ($schema === '') {
            printFinding(
                $file,
                $dom->documentElement?->getLineNo() ?? 1,
                'Schema',
                'Missing xsi:noNamespaceSchemaLocation',
                $schema
            );
            $failedFiles++;
            $errorCount++;
            continue;
        }

        try {
            $resolvedSchema = resolveSchema($schema);
            $validationSchema = resolveValidationSchema($schema, $resolvedSchema);
            $originalDirectory = null;
            if (!str_starts_with($validationSchema, 'urn:')) {
                $originalDirectory = getcwd();
                if ($originalDirectory === false || !chdir(dirname($validationSchema))) {
                    throw new RuntimeException("Unable to access schema directory: {$validationSchema}");
                }
            }
            try {
                $errors = str_starts_with($schema, 'urn:magento:mftf:')
                    ? MftfDom::validateDomDocument($dom, $validationSchema)
                    : MagentoDom::validateDomDocument($dom, $validationSchema);
            } finally {
                if ($originalDirectory !== null) {
                    chdir($originalDirectory);
                }
            }
        } catch (DomainException $exception) {
            printFinding(
                $file,
                $dom->documentElement?->getLineNo() ?? 1,
                'Schema',
                $exception->getMessage(),
                $schema
            );
            $failedFiles++;
            $errorCount++;
            continue;
        }

        foreach ($errors as $error) {
            $normalized = normalizeSchemaError((string) $error);
            printFinding($file, $normalized['line'], 'XSD', $normalized['message'], $schema);
            $fileFailed = true;
            $errorCount++;
        }
        if ($fileFailed) {
            $failedFiles++;
        }
    }

    return ['files' => $failedFiles, 'errors' => $errorCount];
}

try {
    $options = parseOptions(array_slice($argv, 1));
    $projectRoot = dirname(__DIR__, 2);
    require $projectRoot . '/app/bootstrap.php';
    $discoveryRoot = normalizeExistingPath(getenv('XML_SCHEMA_DISCOVERY_ROOT') ?: $projectRoot);
    $files = discoverXmlFiles($discoveryRoot, $options['module'], $options['path']);

    if ($files === []) {
        printf("No project Magento XML files found; XML schema validation skipped.\n");
        exit(0);
    }

    $result = validateFiles($files);
    if ($result['files'] > 0) {
        printf(
            "XML schema validation failed: %d file(s), %d error(s).\n",
            $result['files'],
            $result['errors']
        );
        exit(1);
    }

    printf("XML schema validation passed: %d file(s).\n", count($files));
    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(2);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Magento XML validation infrastructure unavailable: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}
