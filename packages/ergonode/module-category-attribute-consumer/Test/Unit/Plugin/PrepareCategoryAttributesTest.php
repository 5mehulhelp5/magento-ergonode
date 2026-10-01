<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Plugin;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use Ergonode\CategoryAttributeConsumer\Plugin\PrepareCategoryAttributes;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use PHPUnit\Framework\TestCase;

class PrepareCategoryAttributesTest extends TestCase
{
    public function testPreparesBeforeStreamReadWithoutRequiringAnyCategoryEvents(): void
    {
        $preparation = $this->createMock(CategoryAttributeSourcePreparation::class);
        $preparation->expects(self::once())->method('prepare');
        self::assertNull((new PrepareCategoryAttributes($preparation))->beforeExecute(
            $this->createStub(CategoryEntityStreamImporter::class)
        ));
    }
}
