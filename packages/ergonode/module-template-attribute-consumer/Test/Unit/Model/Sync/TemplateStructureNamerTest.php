<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Sync\CommonTemplateGroup;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureNamer;
use Ergonode\TemplateAttributeConsumer\Model\Template\UnassignedTemplateSection;
use PHPUnit\Framework\TestCase;

class TemplateStructureNamerTest extends TestCase
{
    public function testSyntheticSectionHasExplicitGlobalName(): void
    {
        self::assertSame(
            UnassignedTemplateSection::GROUP_NAME,
            (new TemplateStructureNamer())->buildGroupName(
                UnassignedTemplateSection::CODE,
                UnassignedTemplateSection::CODE,
                true
            )
        );
    }

    public function testRealErgonodeSectionUsesItsErgonodeName(): void
    {
        self::assertSame(
            'Ergonode - Product Data',
            (new TemplateStructureNamer())->buildGroupName('ergonode', 'Product Data')
        );
    }

    public function testCommonGroupHasStableUnprefixedNameAndCode(): void
    {
        $namer = new TemplateStructureNamer();

        self::assertSame('ergonode', $namer->buildGroupCode(CommonTemplateGroup::SECTION_CODE));
        self::assertSame(
            'Ergonode',
            $namer->buildGroupName(CommonTemplateGroup::SECTION_CODE, CommonTemplateGroup::GROUP_NAME)
        );
    }
}
