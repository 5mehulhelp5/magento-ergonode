<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use PhpParser\Error;
use PhpParser\Parser;
use RuntimeException;

final readonly class SourceAnalyzer
{
    public function __construct(
        private Parser $parser,
        private StatementSequenceExtractor $extractor
    ) {
    }

    /**
     * @param array<string, string> $files display path => absolute path
     * @return list<StatementSequence>
     */
    public function analyze(array $files): array
    {
        $sequences = [];
        foreach ($files as $displayPath => $absolutePath) {
            $code = file_get_contents($absolutePath);
            if ($code === false) {
                throw new RuntimeException("Unable to read PHP file: {$displayPath}");
            }
            try {
                $statements = $this->parser->parse($code) ?? [];
            } catch (Error $error) {
                throw new RuntimeException(
                    sprintf('Unable to parse %s: %s', $displayPath, $error->getMessage()),
                    0,
                    $error
                );
            }

            array_push($sequences, ...$this->extractor->extract($displayPath, array_values($statements)));
        }

        return $sequences;
    }
}
