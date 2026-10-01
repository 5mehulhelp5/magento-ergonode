<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Unit\Controller\Adminhtml\Language;

use Ergonode\Language\Api\LanguageStoreMappingSaverInterface;
use Ergonode\Language\Exception\MappingConflictException;
use Ergonode\LanguageAdminUi\Controller\Adminhtml\Language\Save;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SaveTest extends ControllerTestCase
{
    private const string REVISION = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string NEXT_REVISION = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string INVALID_REVISION = 'Missing or invalid language mapping revision. Reload the page.';
    private const string INTERNAL_ERROR = 'Unable to save language mappings. Check the Magento logs for details.';

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayloadWithoutCallingSaver(string $payload, string $message): void
    {
        $saver = $this->createMock(LanguageStoreMappingSaverInterface::class);
        $saver->expects(self::never())->method('save');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        (new Save($this->context(['payload' => $payload], [
            'success' => false, 'message' => $message,
        ]), new Json(), $saver, $logger))->execute();
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPayloads(): array
    {
        $result = [
            'missing payload' => ['', 'Missing language mapping payload.'],
            'blank payload' => ['  ', 'Missing language mapping payload.'],
            'null document' => ['null', 'Invalid language mapping payload.'],
            'scalar document' => ['42', 'Invalid language mapping payload.'],
            'missing mappings' => ['{}', 'Invalid language mapping payload.'],
            'null mappings' => ['{"mappings":null}', 'Invalid language mapping payload.'],
            'scalar mappings' => ['{"mappings":"invalid"}', 'Invalid language mapping payload.'],
            'missing revision' => ['{"mappings":[]}', self::INVALID_REVISION],
        ];
        foreach ([
            'null' => null, 'number' => 123, 'array' => [], 'empty' => '',
            'too short' => str_repeat('a', 63), 'too long' => str_repeat('a', 65),
            'uppercase' => str_repeat('A', 64), 'non-hex' => str_repeat('g', 64),
            'trailing newline' => self::REVISION . "\n",
        ] as $name => $revision) {
            $result[$name . ' revision'] = [
                json_encode(['mappings' => [], 'revision' => $revision], JSON_THROW_ON_ERROR),
                self::INVALID_REVISION,
            ];
        }
        return $result;
    }

    public function testMalformedJsonIsLoggedAndNeverReachesSaver(): void
    {
        $saver = $this->createMock(LanguageStoreMappingSaverInterface::class);
        $saver->expects(self::never())->method('save');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Unexpected error while saving Ergonode language mappings.',
            self::callback(
                static fn (array $context): bool => $context['exception'] instanceof InvalidArgumentException
            )
        );
        (new Save($this->context(['payload' => '{broken-json'], [
            'success' => false, 'message' => self::INTERNAL_ERROR,
        ]), new Json(), $saver, $logger))->execute();
    }

    #[DataProvider('visibilityPayloads')]
    public function testValidRequestReturnsStatsAndNewRevision(mixed $visibility): void
    {
        $mappings = [['left' => ['code' => 'pl_PL'], 'right' => ['code' => '0']]];
        $payload = ['mappings' => $mappings, 'revision' => self::REVISION];
        if ($visibility !== null) {
            $payload['visibility'] = $visibility;
        }
        $stats = ['inserted' => 1, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];
        $saver = $this->createMock(LanguageStoreMappingSaverInterface::class);
        $saver->expects(self::once())->method('save')
            ->with($mappings, is_array($visibility) ? $visibility : [], self::REVISION)
            ->willReturn(['stats' => $stats, 'revision' => self::NEXT_REVISION]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        (new Save($this->context(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)], [
            'success' => true, 'message' => 'Language mappings have been saved.',
            'stats' => $stats, 'revision' => self::NEXT_REVISION,
        ]), new Json(), $saver, $logger))->execute();
    }

    /** @return array<string, array{mixed}> */
    public static function visibilityPayloads(): array
    {
        return [
            'provided visibility' => [[['source' => 'ergo', 'code' => 'pl_PL', 'active' => false]]],
            'omitted visibility' => [null],
            'non-array visibility uses existing empty fallback' => ['invalid'],
        ];
    }

    #[DataProvider('saveFailures')]
    public function testFailureResponseDoesNotClaimSaveOrExposeInternalDetails(string $kind): void
    {
        $exception = match ($kind) {
            'conflict' => new MappingConflictException(),
            'validation' => new LocalizedException(__('Store View is unavailable.')),
            default => new RuntimeException('Private database failure detail.'),
        };
        $saver = $this->createMock(LanguageStoreMappingSaverInterface::class);
        $saver->expects(self::once())->method('save')->willThrowException($exception);
        $logger = $this->createMock(LoggerInterface::class);
        if ($kind === 'unexpected') {
            $logger->expects(self::once())->method('error')->with(
                'Unexpected error while saving Ergonode language mappings.',
                ['exception' => $exception]
            );
        } else {
            $logger->expects(self::never())->method('error');
        }
        $payload = json_encode(['mappings' => [], 'revision' => self::REVISION], JSON_THROW_ON_ERROR);
        (new Save($this->context(['payload' => $payload], [
            'success' => false,
            'message' => $kind === 'unexpected' ? self::INTERNAL_ERROR : $exception->getMessage(),
        ], $kind === 'conflict' ? 409 : null), new Json(), $saver, $logger))->execute();
    }

    /** @return array<string, array{string}> */
    public static function saveFailures(): array
    {
        return ['conflict' => ['conflict'], 'validation' => ['validation'], 'unexpected' => ['unexpected']];
    }
}
