<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Contract;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PhpParser\NodeFinder;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class AclResourceConstantTest extends TestCase
{
    public function testAclResourceLiteralsAreDeclaredOnlyAsClassConstants(): void
    {
        $ergonodeModules = dirname(__DIR__, 4);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ergonodeModules, FilesystemIterator::SKIP_DOTS)
        );
        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php'
                || str_contains($path, DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            foreach ($this->aclLiteralLines((string)file_get_contents($path)) as $lineNumber) {
                $violations[] = sprintf(
                    '%s:%d',
                    substr($path, strlen($ergonodeModules) + 1),
                    $lineNumber
                );
            }
        }

        self::assertSame(
            [],
            $violations,
            "ACL resource literals must be declared as class constants:\n" . implode("\n", $violations)
        );
    }

    /** @return array<string, array{string, int}> */
    public static function declarationCases(): array
    {
        return [
            'typed multiline array constant' => ["class Example { private const array ACL = [\n"
                . "'Ergonode_Core::configuration',\n'Ergonode_Core::mapping',\n]; }", 0],
            'typed scalar constant' => [
                "class Example { public const string ACL = 'Ergonode_Core::configuration'; }", 0
            ],
            'method literal' => [
                "class Example { public function acl() { return 'Ergonode_Core::configuration'; } }", 1
            ],
            'global constant' => ["const ACL = 'Ergonode_Core::configuration';", 1],
            'property array' => ["class Example { public array \$acl = ['Ergonode_Core::configuration']; }", 1],
            'method on same line as constant' => ["class Example { const string ACL = 'Ergonode_Core::configuration'; "
                . "public function acl() { return 'Ergonode_Core::mapping'; } }", 1],
            'comment' => ["// 'Ergonode_Core::configuration'", 0],
        ];
    }

    #[DataProvider('declarationCases')]
    public function testOnlyClassConstantValuesAreAccepted(string $source, int $violations): void
    {
        self::assertCount($violations, $this->aclLiteralLines("<?php\n" . $source));
    }

    /** @return int[] */
    private function aclLiteralLines(string $source): array
    {
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? [];
        $finder = new NodeFinder();
        $declared = [];
        foreach ($finder->findInstanceOf($nodes, ClassConst::class) as $constant) {
            foreach ($finder->findInstanceOf($constant->consts, String_::class) as $literal) {
                $declared[spl_object_id($literal)] = true;
            }
        }
        $violations = [];
        foreach ($finder->findInstanceOf($nodes, String_::class) as $literal) {
            if (!isset($declared[spl_object_id($literal)])
                && preg_match('/^Ergonode_[A-Za-z0-9]+::[a-z0-9_]+$/', $literal->value) === 1
            ) {
                $violations[] = $literal->getStartLine();
            }
        }
        return $violations;
    }
}
