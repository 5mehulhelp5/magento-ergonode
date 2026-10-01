<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationCollisionLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CategoryCreationCollisionLoggerTest extends TestCase
{
    public function testWritesStructuredWarningContext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Magento category creation in Ergonode was skipped due to a code collision.',
            [
                'category_tree_id' => 7,
                'ergonode_category_code' => 'promocja_10_99',
                'skipped_magento_category_id' => 12,
                'skipped_magento_category_label' => 'Promocja 10-99',
                'collision_source' => 'batch',
                'winning_magento_category_id' => 11,
                'winning_magento_category_label' => 'Promocja 10.99',
            ]
        );

        (new CategoryCreationCollisionLogger($logger))->warning(
            7,
            'promocja_10_99',
            12,
            'Promocja 10-99',
            'batch',
            11,
            'Promocja 10.99'
        );
    }
}
