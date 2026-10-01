<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Provider;

use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\Core\Model\Provider\VisibilityProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ErgonodeRelationAttributeProviderTest extends TestCase
{
    public function testReturnsOnlyCachedRelationAttributesWithAdminLanguageLabels(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())
            ->method('from')
            ->with('attribute_cache', ['code', 'scope', 'labels_json'])
            ->willReturnSelf();
        $select->expects(self::once())->method('where')->with('type = ?', 'relation')->willReturnSelf();
        $select->expects(self::once())->method('order')->with('code ASC')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn([
            [
                'code' => 'related_products',
                'scope' => 'local',
                'labels_json' => '{"pl_PL":"Produkty powiązane","en_GB":"Related products"}',
            ],
        ]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('attribute_cache');
        $languageMapping = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMapping->method('getAdminLanguageCode')->willReturn('pl_PL');

        $provider = new ErgonodeAttributeProvider(
            $resource,
            new Json(),
            $languageMapping,
            $this->createStub(VisibilityProvider::class)
        );

        self::assertSame([
            [
                'label' => 'Produkty powiązane',
                'code' => 'related_products',
                'scope' => 'local',
            ],
        ], $provider->getRelationAttributes());
        self::assertSame($provider->getRelationAttributes(), $provider->getRelationAttributes());
    }
}
