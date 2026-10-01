<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupCodeOwnerProvider;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupCodeResolver;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureNamer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class TemplateGroupCodeResolverTest extends TestCase
{
    public function testKeepsReadableCodeWhenItIsAvailable(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->once())
            ->method('find')
            ->with(12, 'ergonode_product_details')
            ->willReturn(null);

        self::assertSame(
            'ergonode_product_details',
            $this->resolver($provider)->resolve('product', 12, 'product-details')
        );
    }

    public function testAddsDeterministicHashWhenNormalizedCodeIsOccupied(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(static function (int $attributeSetId, string $groupCode): ?array {
                self::assertSame(12, $attributeSetId);

                return $groupCode === 'ergonode_foo_bar'
                    ? ['attribute_group_id' => 18, 'template_code' => 'product', 'section_code' => 'foo_bar']
                    : null;
            });

        self::assertSame(
            'ergonode_foo_bar_' . substr(hash('sha256', 'foo-bar'), 0, 8),
            $this->resolver($provider)->resolve('product', 12, 'foo-bar')
        );
    }

    public function testTreatsGroupWithoutOwnershipAsForeign(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->exactly(2))
            ->method('find')
            ->willReturnOnConsecutiveCalls(
                ['attribute_group_id' => 18, 'template_code' => null, 'section_code' => null],
                null
            );

        self::assertSame(
            'ergonode_general_' . substr(hash('sha256', 'general'), 0, 8),
            $this->resolver($provider)->resolve('product', 12, 'general')
        );
    }

    public function testUsesMappedGroupCodeWithoutChangingItsId(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->once())
            ->method('find')
            ->with(12, 'ergonode_general')
            ->willReturn(['attribute_group_id' => 44, 'template_code' => null, 'section_code' => null]);

        self::assertSame(
            'ergonode_general',
            $this->resolver($provider)->resolve('product', 12, 'general', 44)
        );
    }

    public function testKeepsCodeAlreadyOwnedByTheSameMappedSection(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->once())
            ->method('find')
            ->willReturn([
                'attribute_group_id' => 18,
                'template_code' => 'product',
                'section_code' => 'general',
            ]);

        self::assertSame(
            'ergonode_general',
            $this->resolver($provider)->resolve('product', 12, 'general')
        );
    }

    public function testUsesGrowingHashPrefixesUntilCodeIsAvailable(): void
    {
        $sectionCode = 'collision';
        $baseCode = 'ergonode_collision';
        $hash = hash('sha256', $sectionCode);
        $occupiedCodes = [$baseCode];
        foreach ([8, 12, 16, 24] as $hashLength) {
            $occupiedCodes[] = $baseCode . '_' . substr($hash, 0, $hashLength);
        }

        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->exactly(6))
            ->method('find')
            ->willReturnCallback(
                static fn (int $attributeSetId, string $groupCode): ?array => in_array($groupCode, $occupiedCodes, true)
                    ? ['attribute_group_id' => 18, 'template_code' => null, 'section_code' => null]
                    : null
            );

        self::assertSame(
            $baseCode . '_' . substr($hash, 0, 32),
            $this->resolver($provider)->resolve('product', 12, $sectionCode)
        );
    }

    public function testTruncatesReadablePartToKeepHashedCodeWithinLimit(): void
    {
        $sectionCode = str_repeat('a', 300);
        $hash = hash('sha256', $sectionCode);
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->exactly(2))
            ->method('find')
            ->willReturnOnConsecutiveCalls(
                ['attribute_group_id' => 18, 'template_code' => null, 'section_code' => null],
                null
            );

        $resolvedCode = $this->resolver($provider)->resolve('product', 12, $sectionCode);

        self::assertSame(255, strlen($resolvedCode));
        self::assertStringEndsWith('_' . substr($hash, 0, 8), $resolvedCode);
    }

    public function testTreatsReservedCodeAsCollision(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->once())
            ->method('find')
            ->willReturn(null);

        self::assertSame(
            'ergonode_foo_bar_' . substr(hash('sha256', 'foo-bar'), 0, 8),
            $this->resolver($provider)->resolve(
                'product',
                12,
                'foo-bar',
                null,
                ['ergonode_foo_bar' => true]
            )
        );
    }

    public function testTreatsCodeFromPreviouslyMovedGroupAsAvailable(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->once())
            ->method('find')
            ->willReturn([
                'attribute_group_id' => 44,
                'template_code' => 'product',
                'section_code' => 'moving',
            ]);

        self::assertSame(
            'ergonode_foo_bar',
            $this->resolver($provider)->resolve(
                'product',
                12,
                'foo_bar',
                null,
                [],
                [44 => true]
            )
        );
    }

    public function testThrowsClearExceptionWhenAllHashCandidatesAreOccupied(): void
    {
        $provider = $this->createMock(TemplateGroupCodeOwnerProvider::class);
        $provider->expects($this->exactly(6))
            ->method('find')
            ->willReturn(['attribute_group_id' => 18, 'template_code' => null, 'section_code' => null]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Unable to resolve a unique Magento attribute group code for Ergonode section "collision"'
        );

        $this->resolver($provider)->resolve('product', 12, 'collision');
    }

    private function resolver(TemplateGroupCodeOwnerProvider $provider): TemplateGroupCodeResolver
    {
        return new TemplateGroupCodeResolver(new TemplateStructureNamer(), $provider);
    }
}
