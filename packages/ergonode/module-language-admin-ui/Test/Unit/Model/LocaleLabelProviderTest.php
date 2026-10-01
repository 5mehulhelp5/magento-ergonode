<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Unit\Model;

use Ergonode\Language\Model\LanguageCodeNormalizer;
use Ergonode\LanguageAdminUi\Model\LocaleLabelProvider;
use Magento\Framework\Locale\ListsInterface;
use PHPUnit\Framework\TestCase;

class LocaleLabelProviderTest extends TestCase
{
    public function testReturnsLocalizedMagentoLabelForNormalizedLocaleCode(): void
    {
        $localeLists = $this->createMock(ListsInterface::class);
        $localeLists->expects(self::once())->method('getOptionLocales')->willReturn([
            ['value' => 'de_DE', 'label' => 'niemiecki (Niemcy)'],
            ['value' => 'pl_PL', 'label' => 'polski (Polska)'],
            ['value' => 'en_US', 'label' => 'angielski (ANGIELSKI)'],
        ]);
        $provider = new LocaleLabelProvider($localeLists, new LanguageCodeNormalizer());

        self::assertSame('Niemiecki (Niemcy)', $provider->getLabel('de-DE'));
        self::assertSame('Polski (Polska)', $provider->getLabel('pl_PL'));
        self::assertSame('Angielski', $provider->getLabel('en_US'));
    }

    public function testFallsBackToOriginalCodeWhenMagentoDoesNotKnowLocale(): void
    {
        $localeLists = $this->createMock(ListsInterface::class);
        $localeLists->method('getOptionLocales')->willReturn([]);

        self::assertSame(
            'custom_CODE',
            (new LocaleLabelProvider($localeLists, new LanguageCodeNormalizer()))->getLabel('custom_CODE')
        );
    }
}
