<?php

declare(strict_types=1);

namespace Ergonode\Template\Test\Unit\Model;

use Ergonode\Template\Model\TemplateCodeNormalizer;
use Magento\Framework\Filter\TranslitUrl;
use PHPUnit\Framework\TestCase;

class TemplateCodeNormalizerTest extends TestCase
{
    public function testNormalizesMagentoAttributeSetNameToErgonodeCode(): void
    {
        $translitUrl = $this->createMock(TranslitUrl::class);
        $translitUrl->expects(self::once())
            ->method('filter')
            ->with('Lóżko Premium / 2026')
            ->willReturn('Lozko-Premium-2026');

        self::assertSame(
            'lozko_premium_2026',
            (new TemplateCodeNormalizer($translitUrl))->normalize(' Łóżko Premium / 2026 ')
        );
    }
}
