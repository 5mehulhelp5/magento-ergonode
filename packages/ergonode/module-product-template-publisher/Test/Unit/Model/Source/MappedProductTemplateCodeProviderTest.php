<?php

declare(strict_types=1);

namespace Ergonode\ProductTemplatePublisher\Test\Unit\Model\Source;

use Ergonode\ProductTemplatePublisher\Model\Source\MappedProductTemplateCodeProvider;
use Ergonode\Template\Api\TemplateAttributeSetMappingProviderInterface;
use PHPUnit\Framework\TestCase;

class MappedProductTemplateCodeProviderTest extends TestCase
{
    public function testReturnsTemplateMappingsOwnedByTemplate(): void
    {
        $mappingProvider = $this->createMock(TemplateAttributeSetMappingProviderInterface::class);
        $mappingProvider->expects(self::once())
            ->method('getTemplateCodesByAttributeSetIds')
            ->with([4, 17])
            ->willReturn([4 => 'default', 17 => 'summer']);

        self::assertSame(
            [4 => 'default', 17 => 'summer'],
            (new MappedProductTemplateCodeProvider($mappingProvider))
                ->getTemplateCodesByAttributeSetIds([4, 17])
        );
    }
}
