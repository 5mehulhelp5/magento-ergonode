<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Test\Unit\Model\Config;

use Magento\Framework\Model\Context;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Ergonode\Media\Api\GalleryAttributeOptionsInterface;
use Ergonode\MediaAdminUi\Model\Config\Backend\GalleryAttribute as Backend;
use Ergonode\MediaAdminUi\Model\Config\Source\GalleryAttribute as Source;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GalleryAttributeTest extends TestCase
{
    public function testSourceListsGalleryCodesAndLabels(): void
    {
        $attributes = $this->createStub(GalleryAttributeOptionsInterface::class);
        $attributes->method('getOptions')->willReturn(['photos' => 'Photos (photos)']);
        self::assertSame([
            ['value' => '', 'label' => 'Choose a gallery attribute'],
            ['value' => 'photos', 'label' => 'Photos (photos)'],
        ], (new Source($attributes))->toOptionArray());
    }

    public function testUnavailableConnectionDoesNotPreventOpeningConfiguration(): void
    {
        $attributes = $this->createStub(GalleryAttributeOptionsInterface::class);
        $attributes->method('getOptions')->willThrowException(new LocalizedException(__('No connection')));
        self::assertSame([
            ['value' => '', 'label' => 'Gallery list unavailable: No connection'],
        ], (new Source($attributes))->toOptionArray());
    }

    #[DataProvider('invalidSelections')]
    public function testRejectsInvalidSelection(string $scope, string $value): void
    {
        $attributes = $this->createStub(GalleryAttributeOptionsInterface::class);
        $attributes->method('getOptions')->willReturn(['photos' => 'Photos']);
        $backend = $this->backend($attributes);
        $backend->setData('scope', $scope);
        $backend->setValue($value);
        $this->expectException(LocalizedException::class);
        $backend->beforeSave();
    }

    public static function invalidSelections(): array
    {
        return [['default', ''], ['websites', 'photos'], ['stores', 'photos'], ['default', 'image']];
    }

    public function testAcceptsAnExistingGalleryInGlobalScope(): void
    {
        $attributes = $this->createStub(GalleryAttributeOptionsInterface::class);
        $attributes->method('getOptions')->willReturn(['photos' => 'Photos']);
        $backend = $this->backend($attributes);
        $backend->setData('scope', 'default');
        $backend->setValue(' photos ');
        self::assertSame($backend, $backend->beforeSave());
        self::assertSame('photos', $backend->getValue());
    }
    private function backend(GalleryAttributeOptionsInterface $attributes): Backend
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        return new Backend(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class),
            $attributes
        );
    }
}
