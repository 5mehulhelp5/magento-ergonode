<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Contract;

use Ergonode\Media\Model\GraphQl\MultimediaClient;
use PHPUnit\Framework\TestCase;

class MultimediaSchemaContractTest extends TestCase
{
    public function testPinnedContractContainsRequiredStreamAndMediaFields(): void
    {
        $schema = json_decode((string)file_get_contents(
            BP . '/vendor/ergonode/module-publisher/Test/Contract/Fixture/ergonode-schema.json'
        ), true, 512, JSON_THROW_ON_ERROR);

        $stream = $schema['domains']['multimedia']['queries']['multimediaStream'];
        self::assertSame('MultimediaConnection', $stream['returns']);
        foreach (['path', 'name', 'extension', 'mime', 'size', 'url'] as $field) {
            self::assertArrayHasKey($field, $schema['types']['Multimedia']['fields']);
        }
        self::assertArrayHasKey('MultimediaReplaceInput', $schema['types']);
        self::assertStringContainsString('multimediaStream', MultimediaClient::STREAM_QUERY);
    }
}
