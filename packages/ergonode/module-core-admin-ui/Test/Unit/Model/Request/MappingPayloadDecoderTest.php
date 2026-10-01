<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Request;

use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class MappingPayloadDecoderTest extends TestCase
{
    public function testDecodesAndRequiresMappingList(): void
    {
        $decoder = new MappingPayloadDecoder(new Json());
        $payload = $decoder->decode('{"mappings":[{"left":null,"right":null}]}');

        self::assertCount(1, $decoder->requireList($payload, 'mappings', 'Invalid mappings.'));
    }

    public function testRejectsInvalidJsonAsLocalizedInputError(): void
    {
        $this->expectException(LocalizedException::class);

        (new MappingPayloadDecoder(new Json()))->decode('{');
    }
}
