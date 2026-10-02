<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Media\Model\GraphQl\GalleryAttributeOptions;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class GalleryAttributeOptionsTest extends TestCase
{
    public function testFiltersTypesAndReadsAllPages(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $calls = 0;
        $client->expects(self::exactly(2))->method('query')->willReturnCallback(
            static function (string $query, array $variables) use (&$calls): array {
                self::assertSame(GalleryAttributeOptions::QUERY, $query);
                self::assertSame(['after' => $calls === 0 ? null : 'next'], $variables);
                $nodes = $calls++ === 0
                    ? [
                        ['__typename' => 'ImageAttribute', 'code' => 'image'],
                        ['__typename' => 'GalleryAttribute', 'code' => 'photos',
                            'name' => [['language' => 'en', 'value' => 'Photos']]],
                    ]
                    : [['__typename' => 'GalleryAttribute', 'code' => 'details', 'name' => []]];
                return ['attributeStream' => [
                    'edges' => array_map(static fn (array $node): array => ['node' => $node], $nodes),
                    'pageInfo' => ['hasNextPage' => $calls === 1, 'endCursor' => 'next'],
                ]];
            }
        );
        self::assertSame(
            ['details' => 'details', 'photos' => 'Photos (photos)'],
            (new GalleryAttributeOptions($client))->getOptions()
        );
    }

    public function testRepeatedCursorDoesNotLoop(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))->method('query')->willReturn(['attributeStream' => [
            'edges' => [], 'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'same'],
        ]]);
        $this->expectException(LocalizedException::class);
        (new GalleryAttributeOptions($client))->getOptions();
    }
}
