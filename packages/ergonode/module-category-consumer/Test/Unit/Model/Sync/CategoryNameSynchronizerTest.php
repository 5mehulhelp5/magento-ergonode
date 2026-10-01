<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameWriterInterface;
use Ergonode\CategoryConsumer\Model\Sync\CategoryNameSynchronizer;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use PHPUnit\Framework\TestCase;

class CategoryNameSynchronizerTest extends TestCase
{
    public function testMissingTranslationClearsStoreOverrideButPreservesRequiredDefaultName(): void
    {
        $target = $this->createStub(CategoryNameTargetProviderInterface::class);
        $target->method('getAttributeCode')->willReturn('name');
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'en_US', 1 => 'pl_PL', 2 => 'de_DE']);
        $writer = $this->createMock(CategoryNameWriterInterface::class);
        $writer->expects(self::once())->method('write')->with(10, 'name', [1 => 'Krzesła', 2 => null])
            ->willReturn(2);

        self::assertSame(2, (new CategoryNameSynchronizer($target, $languages, $writer))
            ->synchronize(10, ['pl_PL' => 'Krzesła']));
    }

    public function testManualModeDoesNotWriteOrRequireLanguageMapping(): void
    {
        $target = $this->createStub(CategoryNameTargetProviderInterface::class);
        $target->method('getAttributeCode')->willReturn(null);
        $languages = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $languages->expects(self::never())->method('getLanguageStoreMap');
        $writer = $this->createMock(CategoryNameWriterInterface::class);
        $writer->expects(self::never())->method('write');

        self::assertSame(0, (new CategoryNameSynchronizer($target, $languages, $writer))
            ->synchronize(10, ['en_US' => 'Chairs']));
    }
}
