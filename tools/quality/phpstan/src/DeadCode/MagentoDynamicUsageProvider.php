<?php

declare(strict_types=1);

namespace Vendivo\PHPStan\DeadCode;

use DOMDocument;
use DOMElement;
use Magento\Framework\Console\CommandListInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use SplFileInfo;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use Symfony\Component\Console\Command\Command;

// phpcs:ignore Vendivo.Magento.NoFinalApplicationClass.FinalApplicationClass -- Not a Magento interceptor target.
final class MagentoDynamicUsageProvider extends ReflectionBasedMemberUsageProvider
{
    private const string XML_SCHEMA_INSTANCE_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    private const DYNAMIC_NAMESPACE_FRAGMENTS = [
        '\\Api\\',
        '\\Block\\',
        '\\Controller\\',
        '\\Cron\\',
        '\\Model\\Resolver\\',
        '\\Observer\\',
        '\\Plugin\\',
        '\\Setup\\',
        '\\Ui\\',
    ];

    private const DYNAMIC_CLASS_SUFFIXES = [
        'DataProvider',
        'Modifier',
        'Options',
        'OptionSource',
        'Source',
        'SourceProvider',
    ];

    private readonly string $projectCodeDirectory;

    /** @var array<string, true> */
    private readonly array $configuredMessageQueueHandlers;

    /** @var array<string, true> */
    private readonly array $configuredConsoleCommands;

    public function __construct(string $projectCodeDirectory)
    {
        $this->projectCodeDirectory = rtrim(str_replace('\\', '/', $projectCodeDirectory), '/') . '/';
        $configuredUsages = $this->discoverConfiguredUsages();
        $this->configuredMessageQueueHandlers = $configuredUsages['messageQueueHandlers'];
        $this->configuredConsoleCommands = $configuredUsages['consoleCommands'];
    }

    public function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        $class = $method->getDeclaringClass();
        if (!$this->isProjectClass($class)) {
            return null;
        }

        if ($method->getName() === '__construct') {
            if ($this->isConsoleCommand($class)) {
                return $this->configuredConsoleCommandUsage($class);
            }

            return VirtualUsageData::withNote(
                'Magento DI and generated factories instantiate project classes dynamically'
            );
        }

        if ($this->isConsoleCommand($class)) {
            if (!$method->isPrivate() && method_exists(Command::class, $method->getName())) {
                return $this->configuredConsoleCommandUsage($class);
            }

            return null;
        }

        if ($class->isInterface() || $this->implementsDeclaredContract($method)) {
            return VirtualUsageData::withNote('Magento service contract method');
        }

        if ($this->isConfiguredMessageQueueHandler($method)) {
            return VirtualUsageData::withNote('Magento message queue invokes this configured handler dynamically');
        }

        if (!$method->isPrivate() && $this->isDynamicEntrypoint($class->getName())) {
            return VirtualUsageData::withNote('Magento invokes this entrypoint or presentation class dynamically');
        }

        return null;
    }

    public function shouldMarkConstantAsUsed(ReflectionClassConstant $constant): ?VirtualUsageData
    {
        $class = $constant->getDeclaringClass();
        if ($this->isProjectClass($class) && ($class->isInterface() || $this->isDynamicEntrypoint($class->getName()))) {
            return VirtualUsageData::withNote('Magento contract or dynamic entrypoint constant');
        }

        return null;
    }

    public function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
    {
        return $this->dynamicPublicPropertyUsage($property);
    }

    protected function shouldMarkPropertyAsWritten(ReflectionProperty $property): ?VirtualUsageData
    {
        return $this->dynamicPublicPropertyUsage($property);
    }

    private function dynamicPublicPropertyUsage(ReflectionProperty $property): ?VirtualUsageData
    {
        $class = $property->getDeclaringClass();
        if ($property->isPublic() && $this->isProjectClass($class) && $this->isDynamicEntrypoint($class->getName())) {
            return VirtualUsageData::withNote(
                'Magento UI, template, serialization, or metadata may access this public property'
            );
        }

        return null;
    }

    private function implementsDeclaredContract(ReflectionMethod $method): bool
    {
        foreach ($method->getDeclaringClass()->getInterfaces() as $interface) {
            if ($interface->hasMethod($method->getName())) {
                return true;
            }
        }

        return false;
    }

    private function isConfiguredMessageQueueHandler(ReflectionMethod $method): bool
    {
        return isset($this->configuredMessageQueueHandlers[$this->methodIdentifier(
            $method->getDeclaringClass()->getName(),
            $method->getName()
        )]);
    }

    /**
     * @return array{
     *     messageQueueHandlers: array<string, true>,
     *     consoleCommands: array<string, true>
     * }
     */
    private function discoverConfiguredUsages(): array
    {
        $handlers = [];
        $commands = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectCodeDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            if ($file->getFilename() === 'communication.xml') {
                $this->collectCommunicationHandlers($file->getPathname(), $handlers);
            } elseif ($file->getFilename() === 'queue_consumer.xml') {
                $this->collectConsumerHandlers($file->getPathname(), $handlers);
            } elseif ($file->getFilename() === 'di.xml') {
                $this->collectConsoleCommands($file->getPathname(), $commands);
            }
        }

        return [
            'messageQueueHandlers' => $handlers,
            'consoleCommands' => $commands,
        ];
    }

    /** @param array<string, true> $commands */
    private function collectConsoleCommands(string $path, array &$commands): void
    {
        $document = $this->loadXml($path);
        if ($document === null) {
            return;
        }

        foreach ($document->getElementsByTagName('type') as $type) {
            if (!$type instanceof DOMElement) {
                continue;
            }
            if ($this->normalizeClassName($type->getAttribute('name')) !== CommandListInterface::class) {
                continue;
            }

            foreach ($type->getElementsByTagName('argument') as $argument) {
                if (!$argument instanceof DOMElement || $argument->getAttribute('name') !== 'commands') {
                    continue;
                }

                foreach ($argument->getElementsByTagName('item') as $item) {
                    if (!$item instanceof DOMElement || $this->xmlType($item) !== 'object') {
                        continue;
                    }

                    $className = $this->normalizeClassName($item->textContent);
                    if ($className !== '') {
                        $commands[$className] = true;
                    }
                }
            }
        }
    }

    /** @param array<string, true> $handlers */
    private function collectCommunicationHandlers(string $path, array &$handlers): void
    {
        $document = $this->loadXml($path);
        if ($document === null) {
            return;
        }

        foreach ($document->getElementsByTagName('handler') as $handler) {
            $this->addHandler($handlers, $handler->getAttribute('type'), $handler->getAttribute('method'));
        }
    }

    /** @param array<string, true> $handlers */
    private function collectConsumerHandlers(string $path, array &$handlers): void
    {
        $document = $this->loadXml($path);
        if ($document === null) {
            return;
        }

        foreach ($document->getElementsByTagName('consumer') as $consumer) {
            $handler = trim($consumer->getAttribute('handler'));
            $separator = strrpos($handler, '::');
            if ($separator === false) {
                continue;
            }

            $this->addHandler($handlers, substr($handler, 0, $separator), substr($handler, $separator + 2));
        }
    }

    private function loadXml(string $path): ?DOMDocument
    {
        $previousInternalErrors = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();

            return $document->load($path, LIBXML_NONET) ? $document : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }

    /** @param array<string, true> $handlers */
    private function addHandler(array &$handlers, string $className, string $methodName): void
    {
        $className = ltrim(trim($className), '\\');
        $methodName = trim($methodName);
        if ($className === '' || $methodName === '') {
            return;
        }

        $handlers[$this->methodIdentifier($className, $methodName)] = true;
    }

    private function methodIdentifier(string $className, string $methodName): string
    {
        return $className . '::' . $methodName;
    }

    /** @param ReflectionClass<object> $class */
    private function isConsoleCommand(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(Command::class);
    }

    /** @param ReflectionClass<object> $class */
    private function configuredConsoleCommandUsage(ReflectionClass $class): ?VirtualUsageData
    {
        if (!isset($this->configuredConsoleCommands[$this->normalizeClassName($class->getName())])) {
            return null;
        }

        return VirtualUsageData::withNote('Magento invokes this command through CommandListInterface DI configuration');
    }

    private function normalizeClassName(string $className): string
    {
        return ltrim(trim($className), '\\');
    }

    private function xmlType(DOMElement $element): string
    {
        return $element->getAttributeNS(self::XML_SCHEMA_INSTANCE_NAMESPACE, 'type')
            ?: $element->getAttribute('xsi:type');
    }

    /** @param ReflectionClass<object> $class */
    private function isProjectClass(ReflectionClass $class): bool
    {
        $filename = $class->getFileName();

        return $filename !== false
            && str_starts_with(str_replace('\\', '/', $filename), $this->projectCodeDirectory);
    }

    private function isDynamicEntrypoint(string $className): bool
    {
        foreach (self::DYNAMIC_NAMESPACE_FRAGMENTS as $fragment) {
            if (str_contains($className, $fragment)) {
                return true;
            }
        }

        foreach (self::DYNAMIC_CLASS_SUFFIXES as $suffix) {
            if (str_ends_with($className, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
