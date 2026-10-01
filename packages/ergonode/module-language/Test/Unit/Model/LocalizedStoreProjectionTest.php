<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Language\Model\LocalizedStoreProjection;
use PHPUnit\Framework\TestCase;

class LocalizedStoreProjectionTest extends TestCase
{
    public function testProjectsStoreValuesToLanguages(): void
    {
        $mapping = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $mapping->method('getLanguageStoreMap')->willReturn([0 => 'en_GB', 2 => 'pl_PL']);

        self::assertSame(
            ['en_GB' => 'Name', 'pl_PL' => 'Nazwa'],
            (new LocalizedStoreProjection($mapping))->project('Name', [2 => 'Nazwa'])
        );
    }

    public function testUsesFirstMappedStoreWhenStoresShareLanguage(): void
    {
        $mapping = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $mapping->method('getLanguageStoreMap')->willReturn([2 => 'en_GB', 0 => 'en_GB', 1 => 'en_GB']);

        self::assertSame(
            ['en_GB' => 'First label'],
            (new LocalizedStoreProjection($mapping))->project(
                'Default label',
                [2 => 'First label', 1 => 'Last label']
            )
        );
    }

    public function testFirstMappedStoreInheritsDefaultValueWithoutOverride(): void
    {
        $mapping = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $mapping->method('getLanguageStoreMap')->willReturn([2 => 'en_GB', 1 => 'en_GB']);

        self::assertSame(
            ['en_GB' => 'Default label'],
            (new LocalizedStoreProjection($mapping))->project(
                'Default label',
                [1 => 'Ignored label']
            )
        );
    }
}
