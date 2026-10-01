<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\ProductAttributeConsumer\Model\Sync\OptionLabelResolver;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class OptionLabelResolverTest extends TestCase
{
    public function testDecodesLabelsAndResolvesAdminDefault(): void
    {
        $languageProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageProvider->method('requireAdminLanguageCode')->willReturn('pl_PL');
        $resolver = new OptionLabelResolver(new Json(), $languageProvider);

        $labels = $resolver->decode('{"pl_PL":" Niebieski ","en_US":"Blue"}');

        self::assertSame(['pl_PL' => 'Niebieski', 'en_US' => 'Blue'], $labels);
        self::assertSame('Niebieski', $resolver->resolveDefault($labels, 'blue'));
        self::assertSame('blue', $resolver->resolveDefault([], ' blue '));
    }

    public function testInvalidPayloadProducesNoLabels(): void
    {
        $resolver = new OptionLabelResolver(
            new Json(),
            $this->createStub(LanguageStoreMappingProviderInterface::class)
        );

        self::assertSame([], $resolver->decode('{invalid'));
    }
}
