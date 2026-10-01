<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Template;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateSectionNameResolver;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class TemplateSectionNameResolverTest extends TestCase
{
    public function testResolvesNameForConfiguredDefaultLocale(): void
    {
        $resolver = $this->resolver('pl_PL');

        self::assertSame('Dane techniczne', $resolver->resolve(
            '{"name":[{"language":"en_GB","value":"Technical data"},{"language":"pl_PL","value":"Dane techniczne"}]}',
            'technical_data'
        ));
    }

    public function testFallsBackToFirstAvailableTranslationAndThenSectionCode(): void
    {
        $resolver = $this->resolver('de_DE');

        self::assertSame('Technical data', $resolver->resolve(
            '{"name":[{"language":"en_GB","value":"Technical data"}]}',
            'technical_data'
        ));
        self::assertSame('technical_data', $resolver->resolve('{"name":[]}', 'technical_data'));
        self::assertSame('technical_data', $resolver->resolve('{invalid', 'technical_data'));
    }

    public function testResolvesAllUniqueTranslatedNames(): void
    {
        $resolver = $this->resolver('pl_PL');

        self::assertSame(
            ['Technical data', 'Dane techniczne'],
            $resolver->resolveAll(
                '{"name":[{"language":"en_GB","value":"Technical data"},'
                . '{"language":"pl_PL","value":"Dane techniczne"},'
                . '{"language":"en_US","value":"Technical data"}]}'
            )
        );
        self::assertSame(['Simple name'], $resolver->resolveAll('{"name":" Simple name "}'));
        self::assertSame([], $resolver->resolveAll('{invalid'));
    }

    private function resolver(string $adminLocale): TemplateSectionNameResolver
    {
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getAdminLanguageCode')->willReturn($adminLocale);

        return new TemplateSectionNameResolver(new Json(), $languageMappingProvider);
    }
}
