<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use InvalidArgumentException;
use PhpParser\ParserFactory;
use Throwable;

final class ConsoleApplication
{
    /** @var list<string> */
    private readonly array $defaultPaths;

    private readonly ReportFormatter $formatter;

    /** @param list<string> $defaultPaths */
    public function __construct(
        private readonly string $projectRoot,
        array $defaultPaths,
        ?ReportFormatter $formatter = null
    ) {
        $this->defaultPaths = $defaultPaths;
        $this->formatter = $formatter ?? new ReportFormatter();
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $requestedFormat = $this->requestedFormat($arguments);
        try {
            $options = $this->parseArguments($arguments);
            if ($options['help']) {
                $this->printHelp();

                return 0;
            }

            $files = (new SourceScanner($this->projectRoot))->scan($options['paths'], $options['includeTests']);
            $sequences = (new SourceAnalyzer(
                (new ParserFactory())->createForNewestSupportedVersion(),
                new StatementSequenceExtractor()
            ))->analyze($files);
            $groups = (new DuplicateDetector(
                new AstNormalizer($options['ignoreLiterals']),
                new StatementMetric()
            ))->detect($sequences, $options['minimumScore'], $options['minimumOccurrences']);
            $report = new AnalysisReport(count($files), $options['minimumScore'], $groups);
            echo $this->formatter->format($report, $options['format'], $options['limit']);

            return $options['failOnDuplicates'] && $report->hasDuplicates() ? 1 : 0;
        } catch (Throwable $throwable) {
            echo $this->formatter->formatError($throwable->getMessage(), $requestedFormat);

            return 2;
        }
    }

    /**
     * @param list<string> $arguments
     * @return array{
     *     paths: list<string>,
     *     minimumScore: int,
     *     minimumOccurrences: int,
     *     format: string,
     *     ignoreLiterals: bool,
     *     includeTests: bool,
     *     failOnDuplicates: bool,
     *     limit: ?int,
     *     help: bool
     * }
     */
    private function parseArguments(array $arguments): array
    {
        $options = [
            'paths' => [],
            'minimumScore' => 40,
            'minimumOccurrences' => 2,
            'format' => 'text',
            'ignoreLiterals' => false,
            'includeTests' => false,
            'failOnDuplicates' => false,
            'limit' => null,
            'help' => false,
        ];

        foreach ($arguments as $argument) {
            if ($argument === '--help' || $argument === '-h') {
                $options['help'] = true;
            } elseif ($argument === '--ignore-literals') {
                $options['ignoreLiterals'] = true;
            } elseif ($argument === '--include-tests') {
                $options['includeTests'] = true;
            } elseif ($argument === '--fail-on-duplicates') {
                $options['failOnDuplicates'] = true;
            } elseif (str_starts_with($argument, '--min-score=')) {
                $options['minimumScore'] = $this->positiveInteger($argument, '--min-score=');
            } elseif (str_starts_with($argument, '--min-occurrences=')) {
                $options['minimumOccurrences'] = $this->positiveInteger($argument, '--min-occurrences=');
            } elseif (str_starts_with($argument, '--limit=')) {
                $options['limit'] = $this->nonNegativeInteger($argument, '--limit=');
            } elseif (str_starts_with($argument, '--format=')) {
                $options['format'] = substr($argument, strlen('--format='));
                if (!in_array($options['format'], ['text', 'json', 'ai'], true)) {
                    throw new InvalidArgumentException('The --format option accepts text, json or ai.');
                }
            } elseif (str_starts_with($argument, '-')) {
                throw new InvalidArgumentException("Unknown option: {$argument}");
            } else {
                $options['paths'][] = $argument;
            }
        }

        if ($options['minimumOccurrences'] < 2) {
            throw new InvalidArgumentException('The minimum occurrence count must be at least two.');
        }
        if ($options['paths'] === []) {
            $options['paths'] = $this->defaultPaths;
        }

        return $options;
    }

    private function positiveInteger(string $argument, string $prefix): int
    {
        $value = substr($argument, strlen($prefix));
        if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            throw new InvalidArgumentException("Expected a positive integer in {$argument}");
        }

        return (int) $value;
    }

    private function nonNegativeInteger(string $argument, string $prefix): int
    {
        $value = substr($argument, strlen($prefix));
        if (preg_match('/^(?:0|[1-9][0-9]*)$/', $value) !== 1) {
            throw new InvalidArgumentException("Expected a non-negative integer in {$argument}");
        }

        return (int) $value;
    }

    /** @param list<string> $arguments */
    private function requestedFormat(array $arguments): string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--format=')) {
                $format = substr($argument, strlen('--format='));

                return in_array($format, ['text', 'json', 'ai'], true) ? $format : 'text';
            }
        }

        return 'text';
    }

    private function printHelp(): void
    {
        echo <<<'HELP'
Usage: php dev/tools/code-duplicates/detect.php [options] [path ...]

Options:
  --min-score=N          Minimum structural score (default: 40)
  --min-occurrences=N    Minimum number of occurrences (default: 2)
  --format=text|json|ai  Human, machine-readable or concise agent output (default: text)
  --limit=N              Limit text/ai groups; 0 shows all (defaults: text 10, ai 5)
  --ignore-literals      Treat string, integer and float values as placeholders
  --include-tests        Include files under Test/ and Tests/
  --fail-on-duplicates   Exit with status 1 when duplicates are found
  -h, --help             Show this help

Variable names, formatting and comments are always ignored.
HELP;
        echo PHP_EOL;
    }
}
