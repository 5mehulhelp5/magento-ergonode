<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates\Test;

use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vendivo\CodeDuplicates\AstNormalizer;
use Vendivo\CodeDuplicates\DuplicateDetector;
use Vendivo\CodeDuplicates\DuplicateGroup;
use Vendivo\CodeDuplicates\DuplicateOccurrence;
use Vendivo\CodeDuplicates\StatementMetric;
use Vendivo\CodeDuplicates\StatementSequence;
use Vendivo\CodeDuplicates\StatementSequenceExtractor;

#[CoversClass(DuplicateDetector::class)]
#[CoversClass(AstNormalizer::class)]
final class DuplicateDetectorTest extends TestCase
{
    public function testDetectsSameStructureWithRenamedVariables(): void
    {
        $groups = $this->detect([
            'Customer.php' => <<<'PHP'
                <?php
                final class Customer
                {
                    public function run($customer): void
                    {
                        if (!$customer->isActive()) {
                            return;
                        }
                        $this->send($customer);
                    }
                }
                PHP,
            'User.php' => <<<'PHP'
                <?php
                final class User
                {
                    public function run($user): void
                    {
                        if (!$user->isActive()) {
                            return;
                        }
                        $this->send($user);
                    }
                }
                PHP,
        ], 8);

        self::assertCount(1, $groups);
        self::assertSame(2, $groups[0]->occurrenceCount());
        self::assertSame(['Customer.php', 'User.php'], array_map(
            static fn (DuplicateOccurrence $occurrence): string => $occurrence->file,
            $groups[0]->occurrences()
        ));
    }

    public function testPreservesVariableRelationshipsAcrossStatements(): void
    {
        $groups = $this->detect([
            'Repeated.php' => <<<'PHP'
                <?php
                function repeated($item): void
                {
                    consume($item);
                    consume($item);
                }
                PHP,
            'Changed.php' => <<<'PHP'
                <?php
                function changed($first, $second): void
                {
                    consume($first);
                    consume($second);
                }
                PHP,
        ], 8);

        self::assertSame([], $groups);
    }

    public function testCanIgnoreLiteralValues(): void
    {
        $sources = [
            'Created.php' => <<<'PHP'
                <?php
                function created(): void
                {
                    record('created');
                    finish();
                }
                PHP,
            'Updated.php' => <<<'PHP'
                <?php
                function updated(): void
                {
                    record('updated');
                    finish();
                }
                PHP,
        ];

        self::assertSame([], $this->detect($sources, 7));
        self::assertCount(1, $this->detect($sources, 7, true));
    }

    public function testSuppressesInnerDuplicateCoveredByTheSameOuterOccurrences(): void
    {
        $groups = $this->detect([
            'First.php' => <<<'PHP'
                <?php
                function first($item): void
                {
                    if ($item->isReady()) {
                        prepare($item);
                        execute($item);
                        finish($item);
                    }
                }
                PHP,
            'Second.php' => <<<'PHP'
                <?php
                function second($entity): void
                {
                    if ($entity->isReady()) {
                        prepare($entity);
                        execute($entity);
                        finish($entity);
                    }
                }
                PHP,
        ], 8);

        self::assertCount(1, $groups);
        self::assertSame(1, $groups[0]->statementCount);
        self::assertSame(2, $groups[0]->occurrenceCount());
    }

    public function testPreservesNonUtf8LiteralBytes(): void
    {
        $invalidByte = chr(255);
        $groups = $this->detect([
            'First.php' => "<?php function first(): void { record('{$invalidByte}'); finish(); }",
            'Second.php' => "<?php function second(): void { record('{$invalidByte}'); finish(); }",
        ], 7);

        self::assertCount(1, $groups);
    }

    /**
     * @param array<string, string> $sources
     * @return list<DuplicateGroup>
     */
    private function detect(array $sources, int $minimumScore, bool $ignoreLiterals = false): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $extractor = new StatementSequenceExtractor();
        $sequences = [];
        foreach ($sources as $file => $source) {
            $statements = $parser->parse($source) ?? [];
            array_push($sequences, ...$extractor->extract($file, array_values($statements)));
        }

        return (new DuplicateDetector(
            new AstNormalizer($ignoreLiterals),
            new StatementMetric()
        ))->detect($sequences, $minimumScore);
    }
}
