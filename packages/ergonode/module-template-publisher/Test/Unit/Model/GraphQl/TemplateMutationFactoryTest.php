<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Test\Unit\Model\GraphQl;

use Ergonode\TemplatePublisher\Model\GraphQl\TemplateMutationFactory;
use PHPUnit\Framework\TestCase;

class TemplateMutationFactoryTest extends TestCase
{
    public function testBuildsTemplateCreateMutation(): void
    {
        $operation = (new TemplateMutationFactory())->create('summer_collection', [
            'pl_PL' => 'Kolekcja letnia',
            'en_GB' => 'Summer collection',
        ]);

        self::assertSame('templateCreate', $operation->getField());
        self::assertSame(['template.code'], $operation->getResponseFields());
        self::assertSame('TemplateCreateInput!', $operation->getVariables()['input']->getType());
        self::assertSame([
            'code' => 'summer_collection',
            'name' => [
                ['language' => 'pl_PL', 'value' => 'Kolekcja letnia'],
                ['language' => 'en_GB', 'value' => 'Summer collection'],
            ],
        ], $operation->getVariables()['input']->getValue());
    }
}
