<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\ProductPublisher\Model\Sync\ProductMutationFailureReporter;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\MutationVariable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ProductMutationFailureReporterTest extends TestCase
{
    public function testUnknownErrorKeepsOperationContextAndCorrelatesSanitizedLog(): void
    {
        $context = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->willReturnCallback(
            static function (string $message, array $data) use (&$context): void {
                self::assertSame('Ergonode product publication operation failed.', $message);
                $context = $data;
            }
        );
        $operation = new MutationOperation(
            'productAddAttributeValueTranslationsText',
            ['input' => new MutationVariable('Input!', ['value' => 'private-payload'])],
            ['product.sku'],
            ['entity_sku' => '1000000144', 'attribute_code' => 'name', 'language' => 'en_GB']
        );
        $result = new MutationResult(
            MutationResultInterface::STATUS_VALIDATION_FAILURE,
            'mutation_2',
            $operation,
            errors: [[
                'message' => 'An unknown error occurred.',
                'path' => ['mutation_2', 0, 'product'],
                'extensions' => ['code' => 'INTERNAL_ERROR', 'debug' => ['trace' => 'private-trace']],
            ]],
            attempts: 2
        );

        $message = (new ProductMutationFailureReporter($logger))->describe($result);

        self::assertStringContainsString('The API did not provide its cause.', $message);
        self::assertStringContainsString('productAddAttributeValueTranslationsText', $message);
        self::assertStringContainsString('Attribute: name.', $message);
        self::assertStringContainsString('Language: en_GB.', $message);
        self::assertStringContainsString('Product SKU: 1000000144.', $message);
        self::assertStringContainsString('Code: INTERNAL_ERROR.', $message);
        self::assertStringContainsString('Path: mutation_2.0.product.', $message);
        self::assertStringContainsString('Log reference: ' . $context['reference'], $message);
        self::assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $context['reference']);
        self::assertSame(2, $context['attempts']);
        self::assertSame('An unknown error occurred.', $context['errors'][0]['message']);
        self::assertStringNotContainsString('private-payload', json_encode($context, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('private-trace', json_encode($context, JSON_THROW_ON_ERROR));
    }

    public function testIncludesAllApiErrorsAndTransportDetails(): void
    {
        $result = new MutationResult(
            MutationResultInterface::STATUS_PERMANENT_FAILURE,
            'create',
            new MutationOperation('productCreateSimple', [], []),
            errors: [
                ['message' => 'Invalid template.', 'extensions' => ['code' => 'INVALID_TEMPLATE']],
                ['message' => 'Access denied.', 'extensions' => ['http_status' => 403, 'failure_type' => 'http']],
            ]
        );

        $message = (new ProductMutationFailureReporter(new NullLogger()))->describe($result);

        self::assertStringContainsString('Invalid template.', $message);
        self::assertStringContainsString('Access denied.', $message);
        self::assertStringContainsString('Code: INVALID_TEMPLATE.', $message);
        self::assertStringContainsString('HTTP: 403.', $message);
        self::assertStringContainsString('Type: http.', $message);
    }

    public function testMissingErrorDetailsStillIdentifyTheFailedOperation(): void
    {
        $result = new MutationResult(
            MutationResultInterface::STATUS_UNRESOLVED,
            'template',
            new MutationOperation('productSetTemplate', [], [])
        );

        $message = (new ProductMutationFailureReporter(new NullLogger()))->describe($result);

        self::assertStringContainsString('productSetTemplate', $message);
        self::assertStringContainsString('Ergonode returned no error details.', $message);
        self::assertStringContainsString('Result: unresolved.', $message);
    }
}
