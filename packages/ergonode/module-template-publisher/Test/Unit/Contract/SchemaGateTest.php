<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class SchemaGateTest extends TestCase
{
    private const string CONTRACT_HASH = 'faeeedb576fed1212fb1214183996016b9fac021c160eaebeac3ac77869a9156';

    public function testStructureWriteGateRemainsClosed(): void
    {
        $schema = $this->loadSchema();

        self::assertSame(self::CONTRACT_HASH, $schema['contractHash']);
        self::assertSame(
            ['templateCreate', 'templateDelete', 'templateSetName'],
            array_keys($schema['domains']['templates']['mutations'])
        );
        self::assertSame(
            ['code', 'name'],
            array_keys($schema['types']['TemplateCreateInput']['inputFields'])
        );
        self::assertSame([], $schema['domains']['sections']['mutations']);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSchema(): array
    {
        return json_decode((string)file_get_contents(
            dirname(__DIR__, 6)
                . '/vendor/ergonode/module-publisher/Test/Contract/Fixture/ergonode-schema.json'
        ), true, 512, JSON_THROW_ON_ERROR);
    }
}
