<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Model\Config;

use Ergonode\ProductCategoryPublisher\Model\Config\CategoryPublicationConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryPublicationConfigTest extends TestCase
{
    #[DataProvider('modeProvider')]
    public function testOnlyExplicitMatchModeAllowsRemoval(string $configuredValue, string $mode, bool $removal): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::exactly(2))
            ->method('getValue')
            ->with('ergonode_products/publication/category_mode')
            ->willReturn($configuredValue);
        $config = new CategoryPublicationConfig($scopeConfig);

        self::assertSame($mode, $config->getMode());
        self::assertSame($removal, $config->shouldRemoveMissingCategories());
    }

    /** @return array<string, array{string, string, bool}> */
    public static function modeProvider(): array
    {
        return [
            'keep' => ['keep', CategoryPublicationConfig::MODE_KEEP, false],
            'match' => ['match', CategoryPublicationConfig::MODE_MATCH, true],
            'missing' => ['', CategoryPublicationConfig::MODE_KEEP, false],
            'invalid' => ['remove_everything', CategoryPublicationConfig::MODE_KEEP, false],
        ];
    }
}
