<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Sync;

use Ergonode\ProductAttribute\Model\Sync\OptionMatchKeyResolver;
use PHPUnit\Framework\TestCase;

class OptionMatchKeyResolverTest extends TestCase
{
    public function testNormalizesLabelsIdenticallyAcrossSources(): void
    {
        $resolver = new OptionMatchKeyResolver();
        $mapping = ['magento_type' => 'select'];

        self::assertSame(
            $resolver->resolve($mapping, ['label' => 'To & owo', 'type' => 'option'], 'ergonode'),
            $resolver->resolve($mapping, ['label' => '  TO  &   OWO ', 'type' => 'option'], 'magento')
        );
        self::assertNotSame(
            $resolver->resolve($mapping, ['label' => 'Biały', 'type' => 'option'], 'ergonode'),
            $resolver->resolve($mapping, ['label' => 'Bialy', 'type' => 'option'], 'magento')
        );
    }

    public function testMatchesLocalizedBooleanValuesToNativeIds(): void
    {
        $resolver = new OptionMatchKeyResolver();
        $mapping = ['magento_type' => 'boolean'];

        self::assertSame(
            $resolver->resolve(
                $mapping,
                ['code' => 'enabled', 'label' => 'Tak', 'type' => 'option'],
                'ergonode'
            ),
            $resolver->resolve(
                $mapping,
                ['code' => 'option_1', 'label' => 'Yes', 'type' => 'option'],
                'magento'
            )
        );
    }

    public function testMatchesVisibilityByNativeValue(): void
    {
        $resolver = new OptionMatchKeyResolver();
        $mapping = [
            'ergonode_attribute_code' => 'visibility',
            'magento_attribute_code' => 'visibility',
            'magento_type' => 'select',
        ];

        self::assertSame(
            $resolver->resolve(
                $mapping,
                ['code' => 'option_4', 'label' => 'Catalog, Search', 'type' => 'option'],
                'ergonode'
            ),
            $resolver->resolve(
                $mapping,
                ['code' => 'option_4', 'scope' => 'VALUE 4', 'type' => 'option'],
                'magento'
            )
        );
    }
}
