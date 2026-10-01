<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingPreparer;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingSaver;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoAttributeCreator;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingNormalizer;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver as MappingSaver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeMappingSaverTest extends TestCase
{
    public function testValidatesBeforeCreatingAndPersistsResolvedMetadata(): void
    {
        $source = ['code' => 'color', 'type' => 'select'];
        $prepared = [['left' => $source, 'right' => $source]];
        $preparer = $this->createStub(AttributeMappingPreparer::class);
        $preparer->method('prepare')->willReturnCallback(
            static function (array $mappings, array &$pending) use ($source, $prepared): array {
                $pending['color'] = $source;

                return $prepared;
            }
        );
        $validated = false;
        $normalizer = $this->createMock(AttributeMappingNormalizer::class);
        $normalizer->expects(self::once())->method('normalize')->with($prepared)->willReturnCallback(
            static function () use (&$validated): array {
                $validated = true;

                return [];
            }
        );
        $creator = $this->createMock(MagentoAttributeCreator::class);
        $creator->expects(self::once())->method('createFromErgonodeAttribute')->with($source)->willReturnCallback(
            static function () use (&$validated, $source): array {
                self::assertTrue($validated);

                return $source;
            }
        );
        $saver = $this->createMock(MappingSaver::class);
        $saver->expects(self::once())->method('save')->with($prepared, [])->willReturn(['inserted' => 1]);

        self::assertSame(
            ['inserted' => 1],
            (new AttributeMappingSaver(
                $preparer,
                $normalizer,
                $creator,
                $saver
            ))->save([['left' => $source, 'right' => ['pending_create' => true]]], [])
        );
    }

    public function testAutomaticAdditionCannotCreateMagentoAttributes(): void
    {
        $preparer = $this->createStub(AttributeMappingPreparer::class);
        $preparer->method('prepare')->willReturnCallback(
            static function (array $mappings, array &$pending): array {
                $pending['color'] = ['code' => 'color'];

                return [];
            }
        );
        $creator = $this->createMock(MagentoAttributeCreator::class);
        $creator->expects(self::never())->method('createFromErgonodeAttribute');
        $saver = $this->createMock(MappingSaver::class);
        $saver->expects(self::never())->method('saveAdditions');
        $this->expectException(LocalizedException::class);

        (new AttributeMappingSaver(
            $preparer,
            $this->createStub(AttributeMappingNormalizer::class),
            $creator,
            $saver
        ))->saveAdditions([['right' => ['pending_create' => true]]]);
    }
}
