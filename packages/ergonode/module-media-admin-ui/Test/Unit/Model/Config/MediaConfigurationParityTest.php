<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Test\Unit\Model\Config;

use Ergonode\Media\Api\ImageAttributeOptionsInterface;
use Ergonode\MediaAdminUi\Model\Config\AdditionalImagesValue;
use Ergonode\MediaAdminUi\Model\Config\Backend\RolePosition;
use Ergonode\ProductMedia\Model\Config\GalleryConfig;
use Ergonode\ProductMedia\Model\Config\ImageRulesNormalizer;
use Ergonode\ProductMedia\Exception\InvalidMediaConfigurationException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaConfigurationParityTest extends TestCase
{
    #[DataProvider('validRules')]
    public function testPanelAndImportProduceTheSameRules(array $rows, array $expected): void
    {
        $json = new Json();
        $admin = $this->codec()->serialize($rows);
        $runtime = $this->runtime([GalleryConfig::XML_PATH_IMAGES => $json->serialize($rows)]);
        self::assertSame($expected, $runtime->getAdditionalImages());
        $stored = $json->unserialize($admin);
        self::assertSame($expected, array_column($stored, 'position', 'attribute'));
    }

    public static function validRules(): array
    {
        return [[[], []],
            [[['attribute' => ' back ', 'position' => '02']], ['back' => 2]],
            [['__empty' => [], 'row' => ['attribute' => 'front', 'position' => 65535]], ['front' => 65535]]];
    }

    #[DataProvider('invalidRules')]
    public function testBothRejectInvalidRulesWithTheSameUnderlyingReason(array $rows): void
    {
        try {
            $this->codec()->serialize($rows);
            self::fail('Admin must reject invalid rules.');
        } catch (InvalidMediaConfigurationException $adminError) {
            $runtime = $this->runtime([GalleryConfig::XML_PATH_IMAGES => (new Json())->serialize($rows)]);
            try {
                $runtime->getAdditionalImages();
                self::fail('Runtime must reject the same rules.');
            } catch (InvalidMediaConfigurationException $runtimeError) {
                self::assertStringContainsString(GalleryConfig::XML_PATH_IMAGES, $runtimeError->getMessage());
                self::assertSame($adminError->getMessage(), $runtimeError->getPrevious()->getMessage());
            }
        }
    }

    public static function invalidRules(): array
    {
        return [
            [[['attribute' => 'back', 'position' => 2], ['attribute' => 'back', 'position' => 3]]],
            [[['attribute' => 'back', 'position' => 2], ['attribute' => 'front', 'position' => 2]]],
            [[['attribute' => 'back', 'position' => '2.5']]],
            [[['attribute' => 'back', 'position' => 1]]],
            [[['attribute' => 'back', 'position' => 65536]]],
            [[['attribute' => 'back', 'position' => [2]]]],
            [[['attribute' => 'back', 'position' => true]]],
            [[['attribute' => ['back'], 'position' => 2]]],
            [[['attribute' => 'back']]], [[null]],
        ];
    }

    #[DataProvider('positions')]
    public function testRolePositionUsesTheSameValidatorInPanelAndImport(mixed $value, bool $valid): void
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $backend = new RolePosition($context, $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class), $this->createStub(TypeListInterface::class),
            new ImageRulesNormalizer());
        $backend->setData('scope', 'default')->setValue($value);
        $runtime = $this->runtime([GalleryConfig::XML_PATH_ROLE => 'hover_image', GalleryConfig::XML_PATH_POSITION => $value]);
        if ($valid) {
            $backend->beforeSave();
            self::assertSame($backend->getValue(), $runtime->getAdditionalRole()['position']);
        } else {
            $errors = [];
            foreach ([fn () => $backend->beforeSave(), fn () => $runtime->getAdditionalRole()] as $call) {
                try { $call(); self::fail('Position must be rejected.'); }
                catch (InvalidMediaConfigurationException $e) { $errors[] = $e->getMessage(); }
            }
            self::assertSame($errors[0], $errors[1]);
        }
    }

    public static function positions(): array
    {
        return [[2, true], ['02', true], [65535, true], [1, false], [65536, false], ['2.5', false],
            [null, false], [[2], false], [true, false]];
    }

    #[DataProvider('invalidJson')]
    public function testMalformedStoredConfigurationHasAReadableError(mixed $raw): void
    {
        $this->expectException(InvalidMediaConfigurationException::class);
        $this->expectExceptionMessage(GalleryConfig::XML_PATH_IMAGES);
        $this->runtime([GalleryConfig::XML_PATH_IMAGES => $raw])->getAdditionalImages();
    }

    public static function invalidJson(): array
    {
        return [['{'], ['null'], ['2'], ['"text"'], [['back' => 2]]];
    }

    private function codec(): AdditionalImagesValue
    {
        $options = $this->createStub(ImageAttributeOptionsInterface::class);
        $options->method('getOptions')->willReturn(['back' => 'Back', 'front' => 'Front']);
        return new AdditionalImagesValue($options, new ImageRulesNormalizer(), new Json());
    }

    private function runtime(array $values): GalleryConfig
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(static fn ($path) => $values[$path] ?? null);
        return new GalleryConfig($config, new Json(), new ImageRulesNormalizer());
    }
}
