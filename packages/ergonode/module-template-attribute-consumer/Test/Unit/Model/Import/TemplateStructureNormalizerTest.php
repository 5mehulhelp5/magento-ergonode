<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Attribute\Model\AttributeDataNormalizer;
use Ergonode\TemplateAttributeConsumer\Model\Import\TemplateStructureNormalizer;
use Ergonode\TemplateAttributeConsumer\Model\Template\UnassignedTemplateSection;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class TemplateStructureNormalizerTest extends TestCase
{
    public function testTemplateHashIncludesTranslatedTemplateName(): void
    {
        $normalizer = $this->createNormalizer();
        $template = $this->templateWithSectionName('Dane techniczne');
        $template['name'] = [['language' => 'pl_PL', 'value' => 'Torba']];
        $renamedTemplate = $template;
        $renamedTemplate['name'][0]['value'] = 'Torebka';

        self::assertNotSame(
            $normalizer->normalize($template)['hash'],
            $normalizer->normalize($renamedTemplate)['hash']
        );
    }

    public function testTemplateHashAndSectionLabelsIncludeTranslatedName(): void
    {
        $normalizer = $this->createNormalizer();
        $template = $this->templateWithSectionName('Dane techniczne');
        $renamedTemplate = $this->templateWithSectionName('Specyfikacja');

        $normalized = $normalizer->normalize($template);
        $renamed = $normalizer->normalize($renamedTemplate);

        self::assertSame(['pl_PL' => 'Dane techniczne'], $normalized['sections'][0]['labels']);
        self::assertNotSame($normalized['sections'][0]['hash'], $renamed['sections'][0]['hash']);
        self::assertNotSame($normalized['hash'], $renamed['hash']);
    }

    public function testRootAttributesUseReservedSyntheticSectionWithoutCollidingWithRealErgonodeSection(): void
    {
        $normalizer = $this->createNormalizer();
        $normalized = $normalizer->normalize([
            'code' => 'product',
            'attributeList' => ['edges' => [
                ['node' => ['code' => 'root_attribute']],
                ['node' => ['code' => 'section_attribute']],
            ]],
            'sectionList' => ['edges' => [[
                'node' => [
                    'code' => 'ergonode',
                    'name' => [['language' => 'en_US', 'value' => 'Product Data']],
                    'attributeList' => ['edges' => [
                        ['node' => ['code' => 'section_attribute']],
                    ]],
                ],
            ]]],
        ]);

        self::assertGreaterThan(32, strlen(UnassignedTemplateSection::CODE));
        self::assertSame(UnassignedTemplateSection::CODE, $normalized['sections'][0]['code']);
        self::assertTrue($normalized['sections'][0]['is_synthetic']);
        self::assertSame(
            [['code' => 'root_attribute', 'sort_order' => 1]],
            $normalized['sections'][0]['attributes']
        );
        self::assertSame('ergonode', $normalized['sections'][1]['code']);
        self::assertFalse($normalized['sections'][1]['is_synthetic']);
        self::assertNotSame($normalized['sections'][0]['hash'], $normalized['sections'][1]['hash']);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateWithSectionName(string $name): array
    {
        return [
            'code' => 'product',
            'attributeList' => ['edges' => []],
            'sectionList' => [
                'edges' => [
                    [
                        'node' => [
                            'code' => 'technical_data',
                            'name' => [
                                ['language' => 'pl_PL', 'value' => $name],
                            ],
                            'attributeList' => ['edges' => []],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createNormalizer(): TemplateStructureNormalizer
    {
        return new TemplateStructureNormalizer(
            new Json(),
            new AttributeDataNormalizer()
        );
    }
}
