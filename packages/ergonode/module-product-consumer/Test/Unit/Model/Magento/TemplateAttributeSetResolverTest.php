<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductConsumer\Exception\DependencyUnavailableException;
use Ergonode\ProductConsumer\Model\Magento\TemplateAttributeSetResolver;
use Ergonode\Template\Api\TemplateAttributeSetResolverInterface;
use PHPUnit\Framework\TestCase;

class TemplateAttributeSetResolverTest extends TestCase
{
    public function testReturnsMappingFromTemplateDomain(): void
    {
        $mappingResolver = $this->createStub(TemplateAttributeSetResolverInterface::class);
        $mappingResolver->method('resolveAttributeSetId')->with('summer')->willReturn(17);

        self::assertSame(17, (new TemplateAttributeSetResolver($mappingResolver))->resolve(' summer '));
    }

    public function testRejectsTemplateWithoutActiveMapping(): void
    {
        $mappingResolver = $this->createStub(TemplateAttributeSetResolverInterface::class);
        $mappingResolver->method('resolveAttributeSetId')->willReturn(null);

        $this->expectException(DependencyUnavailableException::class);
        (new TemplateAttributeSetResolver($mappingResolver))->resolve('missing');
    }
}
