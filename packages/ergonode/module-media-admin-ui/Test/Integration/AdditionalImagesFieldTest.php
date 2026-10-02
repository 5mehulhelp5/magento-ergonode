<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Test\Integration;

use Ergonode\Media\Api\ImageAttributeOptionsInterface;
use Ergonode\MediaAdminUi\Block\Adminhtml\System\Config\AdditionalImages;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Fixture\AppArea;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml')]
class AdditionalImagesFieldTest extends TestCase
{
    public function testNativeFieldRendersEscapedProductionConfiguration(): void
    {
        $om = Bootstrap::getObjectManager();
        $source = $this->createStub(ImageAttributeOptionsInterface::class);
        $source->method('getOptions')->willReturn(['back' => '<script>Back</script>']);
        $block = $om->get(LayoutInterface::class)->createBlock(AdditionalImages::class, '', ['attributes' => $source]);
        $form = $om->get(FormFactory::class)->create();
        $element = $form->addField('additional_images', 'text', [
            'name' => 'groups[media][fields][additional_images][value]',
            'label' => 'Additional images',
            'value' => [['attribute' => 'back', 'position' => 2]],
        ]);
        $html = $block->render($element);
        self::assertStringContainsString('data-mage-init=', $html);
        self::assertStringNotContainsString('<script>Back</script>', $html);
        $config = json_decode($block->getMageInitJson(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $config['Ergonode_MediaAdminUi/js/additional-images']['rows'][0]['position']);
        self::assertSame(
            'groups[media][fields][additional_images][value]',
            $config['Ergonode_MediaAdminUi/js/additional-images']['name']
        );
    }
}
