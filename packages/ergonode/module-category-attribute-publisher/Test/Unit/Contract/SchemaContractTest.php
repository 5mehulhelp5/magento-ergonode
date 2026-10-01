<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Contract;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use PHPUnit\Framework\TestCase;

class SchemaContractTest extends TestCase
{
    public function testEveryAttributeMutationAndInputExistsInSnapshot(): void
    {
        $schema = json_decode((string)file_get_contents(
            dirname(__DIR__, 6)
                . '/vendor/ergonode/module-publisher/Test/Contract/Fixture/ergonode-schema.json'
        ), true, 512, JSON_THROW_ON_ERROR);
        $operations = ['categoryAttributeAddAttribute', 'categoryDeleteAttributeValueTranslations'];
        foreach (ErgonodeAttributeTypeInterface::MUTATION_SUFFIXES as $suffix) {
            $operations[] = 'categoryAddAttributeValueTranslations' . $suffix;
        }

        foreach ($operations as $operation) {
            self::assertContains($operation, $schema['operationIndex']['mutations']);
            $input = rtrim(
                $schema['domains']['categories']['mutations'][$operation]['arguments']['input']['type'],
                '!'
            );
            self::assertSame('INPUT_OBJECT', $schema['types'][$input]['kind']);
        }
    }
}
