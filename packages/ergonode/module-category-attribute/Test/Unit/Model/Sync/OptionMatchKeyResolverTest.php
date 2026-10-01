<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Test\Unit\Model\Sync;

use Ergonode\CategoryAttribute\Model\Sync\OptionMatchKeyResolver;
use PHPUnit\Framework\TestCase;

class OptionMatchKeyResolverTest extends TestCase
{
    public function testNormalizesLabelsIdenticallyAcrossSources(): void
    {
        $resolver = new OptionMatchKeyResolver();
        $mapping = ['magento_type' => 'select'];

        self::assertSame(
            $resolver->resolve($mapping, ['label' => 'Biały / Mat', 'type' => 'option'], 'ergonode'),
            $resolver->resolve($mapping, ['label' => ' BIALY-MAT ', 'type' => 'option'], 'magento')
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
}
