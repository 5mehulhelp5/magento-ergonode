<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\CategoryAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryOptionAutoMatcher;
use PHPUnit\Framework\TestCase;

class CategoryOptionAutoMatcherTest extends TestCase
{
    public function testDelegatesCurrentDraftCandidatesToNeutralMatcher(): void
    {
        $left = [['code' => 'navy', 'label' => 'Granatowy']];
        $right = [['code' => 'option_42', 'label' => 'Navy']];
        $result = ['matches' => [['left' => $left[0], 'right' => $right[0]]]];
        $matcher = $this->createMock(OptionAutoMatcherInterface::class);
        $matcher->expects(self::once())->method('suggest')->with(7, $left, $right)->willReturn($result);

        self::assertSame($result, (new CategoryOptionAutoMatcher($matcher))->suggest(7, $left, $right));
    }
}
