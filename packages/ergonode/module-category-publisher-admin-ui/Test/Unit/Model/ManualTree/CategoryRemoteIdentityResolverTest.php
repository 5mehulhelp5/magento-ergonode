<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\Category\Api\CategoryRemoteIdentityProviderInterface;
use Ergonode\Category\Api\CategoryRemoteIdentityWriterInterface;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\RetryableRequestException;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryRemoteIdentityResolver;
use PHPUnit\Framework\TestCase;

class CategoryRemoteIdentityResolverTest extends TestCase
{
    public function testNumericIdentityAndZeroParentAreWrittenAsStrings(): void
    {
        $provider = $this->createStub(CategoryRemoteIdentityProviderInterface::class);
        $provider->method('getIdsByCode')->willReturn([]);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::once())->method('getCategoryIds')->with(['123'])
            ->willReturn([123 => 'numeric-id']);
        $writer = $this->createMock(CategoryRemoteIdentityWriterInterface::class);
        $writer->expects(self::once())->method('saveRemoteIdentities')->with(7, [[
            'code' => '123',
            'remote_id' => 'numeric-id',
            'manual_parent_code' => '0',
            'manual_sort_order' => 0,
            'magento_category_id' => null,
        ]]);
        self::assertSame([123 => 'numeric-id'], (new CategoryRemoteIdentityResolver($provider, $writer, $gateway))
            ->resolve(7, [['code' => '123', 'parent_code' => '0']]));
    }

    public function testOnlyMissingIdentitiesUseRestAndAreCheckpointed(): void
    {
        $items = [[
            'code' => 'furniture',
            'sort_order' => 1,
        ], [
            'code' => 'chairs',
            'parent_code' => 'furniture',
            'sort_order' => 4,
            'magento_category_id' => 12,
        ]];
        $provider = $this->createMock(CategoryRemoteIdentityProviderInterface::class);
        $provider->expects(self::once())->method('getIdsByCode')
            ->with(7, ['furniture', 'chairs'])
            ->willReturn(['furniture' => 'remote-furniture']);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::once())->method('getCategoryIds')
            ->with(['chairs'])->willReturn(['chairs' => 'remote-chairs']);
        $writer = $this->createMock(CategoryRemoteIdentityWriterInterface::class);
        $writer->expects(self::once())->method('saveRemoteIdentities')->with(7, [[
            'code' => 'chairs',
            'remote_id' => 'remote-chairs',
            'manual_parent_code' => 'furniture',
            'manual_sort_order' => 4,
            'magento_category_id' => 12,
        ]]);

        self::assertSame([
            'furniture' => 'remote-furniture',
            'chairs' => 'remote-chairs',
        ], (new CategoryRemoteIdentityResolver($provider, $writer, $gateway))->resolve(7, $items));
    }

    public function testResolvedPrefixIsCheckpointedBeforeRetryableLookupFailure(): void
    {
        $items = [
            ['code' => 'chairs', 'sort_order' => 1],
            ['code' => 'tables', 'sort_order' => 2],
        ];
        $provider = $this->createStub(CategoryRemoteIdentityProviderInterface::class);
        $provider->method('getIdsByCode')->willReturn([]);
        $gateway = $this->createMock(CategoryTreeGateway::class);
        $gateway->expects(self::once())->method('getCategoryIds')->with(['chairs', 'tables'])->willReturnCallback(
            static function (): iterable {
                yield 'chairs' => 'remote-chairs';
                throw new RetryableRequestException('Category is not visible yet.', 2);
            }
        );
        $writer = $this->createMock(CategoryRemoteIdentityWriterInterface::class);
        $writer->expects(self::once())->method('saveRemoteIdentities')->with(7, [[
            'code' => 'chairs',
            'remote_id' => 'remote-chairs',
            'manual_parent_code' => null,
            'manual_sort_order' => 1,
            'magento_category_id' => null,
        ]]);

        $this->expectException(RetryableRequestException::class);
        (new CategoryRemoteIdentityResolver($provider, $writer, $gateway))->resolve(7, $items);
    }
}
