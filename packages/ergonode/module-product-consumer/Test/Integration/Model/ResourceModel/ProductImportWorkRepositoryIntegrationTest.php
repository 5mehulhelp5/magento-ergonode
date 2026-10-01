<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Integration\Model\ResourceModel;

use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\ResourceModel\ProductImportWorkRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class ProductImportWorkRepositoryIntegrationTest extends TestCase
{
    public function testNewerEventCannotBeAcknowledgedByOlderLease(): void
    {
        $repository = Bootstrap::getObjectManager()->get(ProductImportWorkRepository::class);
        $repository->scheduleSynchronizations([[
            'sku' => 'IMPORT-SKU',
            'payload' => ['sku' => 'IMPORT-SKU', '__typename' => 'SimpleProduct'],
        ]]);
        $first = $repository->claim(1, 60);
        self::assertCount(1, $first);
        self::assertSame(ProductImportWorkItem::OPERATION_SYNCHRONIZE, $first[0]->operation);

        $repository->scheduleDeletions([['sku' => 'IMPORT-SKU', 'payload' => null]]);
        self::assertFalse($repository->complete($first[0]));

        $second = $repository->claim(1, 60);
        self::assertCount(1, $second);
        self::assertSame(ProductImportWorkItem::OPERATION_DELETE, $second[0]->operation);
        self::assertTrue($repository->complete($second[0]));
        self::assertFalse($repository->hasClaimableWork());

        $this->expectException(LocalizedException::class);
        $repository->scheduleDeletions([['sku' => '', 'payload' => null]]);
    }
}
