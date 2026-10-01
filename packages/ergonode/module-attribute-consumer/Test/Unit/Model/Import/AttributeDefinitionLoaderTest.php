<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\AttributeDefinitionLoader;
use Ergonode\AttributeConsumer\Model\Import\AttributeNormalizer;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Core\Model\GraphQl\CursorPaginationGuardFactory;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeDefinitionLoaderTest extends TestCase
{
    public function testRejectsMalformedSecondPageInsteadOfReturningPartialDefinitions(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            ['attributeStream' => ['edges' => [['node' => ['code' => 'kept']]],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'next']]],
            ['attributeStream' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null]]]
        );
        $normalizer = $this->createStub(AttributeNormalizer::class);
        $normalizer->method('normalizeAttribute')->willReturn(['code' => 'kept']);
        $loader = $this->loader($client, $normalizer);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('incomplete attributeStream');
        $loader->load(false);
    }

    public function testDeletedStreamReadsScalarCodesAndPreservesCursorOnEmptyTail(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            ['attributeDeletedStream' => ['edges' => [['node' => 'deleted']],
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'deleted-next']]],
            ['attributeDeletedStream' => ['edges' => [],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]]]
        );
        self::assertSame(
            ['cursor' => 'deleted-next', 'changed' => true],
            $this->loader($client, $this->createStub(AttributeNormalizer::class))
            ->changes('attributeDeletedStream', 'old', false)
        );
    }

    private function loader(Client $client, AttributeNormalizer $normalizer): AttributeDefinitionLoader
    {
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['en_GB']);

        return new AttributeDefinitionLoader($client, $normalizer, $languages, new CursorPaginationGuardFactory());
    }
}
