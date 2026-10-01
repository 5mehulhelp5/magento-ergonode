<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationMutationPlanner;
use PHPUnit\Framework\TestCase;
use Ergonode\ProductPublisher\Model\GraphQl\RemoteProductPublicationStateLoader;

class ProductPublicationMutationPlannerTest extends TestCase
{
    public function testOmittedAttributeDoesNotCreateValueOrClearMutation(): void
    {
        $mutations = $this->createMock(ProductMutationFactory::class);
        $mutations->expects(self::never())->method('setValue');
        $mutations->expects(self::never())->method('deleteValueTranslation');

        $state = new ProductState('T-2105', 'simple', 'template', values: []);
        $planner = new ProductPublicationMutationPlanner(
            $mutations,
            $this->createStub(RemoteProductPublicationStateLoader::class)
        );
        $operations = $planner->operations(['T-2105' => $state], []);

        self::assertSame([], $operations);
    }
}
