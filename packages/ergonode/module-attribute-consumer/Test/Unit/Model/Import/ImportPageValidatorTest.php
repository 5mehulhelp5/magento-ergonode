<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\ImportPageValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportPageValidatorTest extends TestCase
{
    public function testValidatesBothResponseTypesThroughOneContract(): void
    {
        $validator = new ImportPageValidator();
        $page = [
            'edges' => [['node' => ['code' => 'color']]],
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => ' terminal '],
        ];

        self::assertSame(
            [$page, false, 'terminal', 100],
            $validator->validateAttributePage(['attributeStream' => $page, '_page_size' => 100])
        );
        self::assertSame(
            [$page, false, 'terminal', 100],
            $validator->validateOptionPage(['attributeOptionList' => $page, '_page_size' => 100])
        );
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidAttributePageProvider')]
    public function testRejectsInvalidAttributePage(array $data, string $message): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($message);

        (new ImportPageValidator())->validateAttributePage($data);
    }

    public function testAllowsRepeatedAttributeUpdatesInTheChangeStream(): void
    {
        $page = [
            'edges' => [['node' => ['code' => 'color']], ['node' => ['code' => 'color']]],
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'terminal'],
        ];
        self::assertSame(
            [$page, false, 'terminal', 200],
            (new ImportPageValidator())->validateAttributePage(['attributeStream' => $page, '_page_size' => 200])
        );
    }

    public function testRejectsMalformedNodesBeforeTheCallerCanPruneOptions(): void
    {
        foreach ([['node' => null], ['node' => ['code' => '']], ['node' => ['code' => 123]]] as $edge) {
            try {
                (new ImportPageValidator())->validateOptionPage([
                    'attributeOptionList' => ['edges' => [$edge], 'pageInfo' => ['hasNextPage' => false]],
                    '_page_size' => 200,
                ]);
                self::fail('Malformed option must not become an empty successful snapshot.');
            } catch (LocalizedException $exception) {
                self::assertStringContainsString('invalid or duplicate code', $exception->getMessage());
            }
        }
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidAttributePageProvider(): array
    {
        return [
            'missing response' => [[], 'missing attributeStream'],
            'missing edges' => [
                ['attributeStream' => ['pageInfo' => []], '_page_size' => 100],
                'missing stream edges',
            ],
            'missing pagination metadata' => [
                ['attributeStream' => ['edges' => []], '_page_size' => 100],
                'missing pagination metadata',
            ],
            'invalid pagination state' => [
                [
                    'attributeStream' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => 1],
                    ],
                    '_page_size' => 100,
                ],
                'invalid pagination state',
            ],
            'invalid cursor' => [
                [
                    'attributeStream' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => 1],
                    ],
                    '_page_size' => 100,
                ],
                'invalid pagination cursor',
            ],
            'missing next cursor' => [
                [
                    'attributeStream' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => true, 'endCursor' => ' '],
                    ],
                    '_page_size' => 100,
                ],
                'no cursor for the next page',
            ],
            'invalid page size' => [
                [
                    'attributeStream' => [
                        'edges' => [],
                        'pageInfo' => ['hasNextPage' => false],
                    ],
                    '_page_size' => 0,
                ],
                'invalid page size',
            ],
        ];
    }

    public function testPreservesOptionResponseErrorContext(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode option response is missing attributeOptionList.');

        (new ImportPageValidator())->validateOptionPage([]);
    }
}
