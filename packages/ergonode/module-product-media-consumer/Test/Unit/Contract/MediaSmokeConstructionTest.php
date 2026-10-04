<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Contract;

use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** Read the smoke source without executing its application bootstrap or database writes. */
class MediaSmokeConstructionTest extends TestCase
{
    public function testAnonymousSmokeClassesProvideRequiredErgonodeInterfaceMethods(): void
    {
        $source = file_get_contents(BP . '/tests/media-import-smoke.php');
        self::assertIsString($source);
        self::assertSame([], $this->interfaceErrors($source));
    }

    public function testMissingUnmanagedImagesModeIsDetectedWithoutRunningTheSmoke(): void
    {
        $source = file_get_contents(BP . '/tests/media-import-smoke.php');
        self::assertIsString($source);
        $oldSource = str_replace(
            '        public function getUnmanagedImagesMode(): UnmanagedImagesMode { return UnmanagedImagesMode::Keep; }' . "\n",
            '',
            $source,
            $replacements
        );
        self::assertSame(1, $replacements);
        self::assertContains(GalleryConfigurationInterface::class . '::getUnmanagedImagesMode is missing from an anonymous smoke class.',
            $this->interfaceErrors($oldSource));
    }

    public function testManualErgonodeConstructionsMatchTheirCurrentSignatures(): void
    {
        $source = file_get_contents(BP . '/tests/media-import-smoke.php');
        self::assertIsString($source);
        self::assertSame([], $this->arityErrors($source));
    }

    public function testMissingImageRolesDependencyIsDetectedWithoutRunningTheSmoke(): void
    {
        $source = file_get_contents(BP . '/tests/media-import-smoke.php');
        self::assertIsString($source);
        $oldSource = str_replace(
            '$om->get(\\Magento\\Store\\Model\\StoreManagerInterface::class),'
                . "\n        " . '$om->get(\\Ergonode\\ProductMedia\\Api\\ImageRolesInterface::class)',
            '$om->get(\\Magento\\Store\\Model\\StoreManagerInterface::class)',
            $source,
            $replacements
        );
        self::assertSame(1, $replacements);
        self::assertContains(ProductFileUsageSynchronizer::class . ': supplied 6, required 7',
            $this->arityErrors($oldSource));
    }

    /** @return list<string> */
    private function arityErrors(string $source): array
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse($source);
        $nodes = (new NodeTraverser(new NameResolver()))->traverse($nodes);
        $errors = [];
        $checked = 0;
        foreach ((new NodeFinder())->findInstanceOf($nodes, New_::class) as $new) {
            if (!$new->class instanceof Name || !str_starts_with($new->class->toString(), 'Ergonode\\')) {
                continue;
            }
            $class = $new->class->toString();
            $constructor = (new ReflectionClass($class))->getConstructor();
            $required = $constructor?->getNumberOfRequiredParameters() ?? 0;
            $maximum = $constructor?->getNumberOfParameters() ?? 0;
            $supplied = count($new->args);
            if ($supplied < $required || ($supplied > $maximum && !($constructor?->isVariadic() ?? false))) {
                $errors[] = $class . ': supplied ' . $supplied . ', required ' . $required;
            }
            ++$checked;
        }
        self::assertGreaterThan(0, $checked, 'No Ergonode constructor calls found in the smoke script.');
        return $errors;
    }

    /** @return list<string> */
    private function interfaceErrors(string $source): array
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse($source);
        $nodes = (new NodeTraverser(new NameResolver()))->traverse($nodes);
        $errors = [];
        $checked = 0;
        foreach ((new NodeFinder())->findInstanceOf($nodes, New_::class) as $new) {
            if (!$new->class instanceof Class_) {
                continue;
            }
            $methods = [];
            foreach ($new->class->getMethods() as $method) {
                $methods[strtolower($method->name->toString())] = true;
            }
            $parent = $new->class->extends !== null ? new ReflectionClass($new->class->extends->toString()) : null;
            foreach ($new->class->implements as $interface) {
                if (!str_starts_with($interface->toString(), 'Ergonode\\')) {
                    continue;
                }
                foreach ((new ReflectionClass($interface->toString()))->getMethods() as $required) {
                    if (isset($methods[strtolower($required->getName())])) {
                        continue;
                    }
                    if ($parent?->hasMethod($required->getName())) {
                        $inherited = $parent->getMethod($required->getName());
                        if ($inherited->isPublic() && !$inherited->isAbstract()) {
                            continue;
                        }
                    }
                    $errors[] = $interface->toString() . '::' . $required->getName()
                        . ' is missing from an anonymous smoke class.';
                }
                ++$checked;
            }
        }
        self::assertGreaterThan(0, $checked, 'No Ergonode interface implementations found in the smoke script.');
        return $errors;
    }
}
