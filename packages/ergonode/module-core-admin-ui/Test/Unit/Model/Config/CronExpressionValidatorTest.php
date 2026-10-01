<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Config;

use Ergonode\CoreAdminUi\Model\Config\CronExpressionValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CronExpressionValidatorTest extends TestCase
{
    public function testNormalizesValidFivePartExpression(): void
    {
        self::assertSame(
            '*/20 * * * *',
            (new CronExpressionValidator())->validate('  */20   * * *  * ')
        );
        self::assertSame(
            '5,15 0-23/2 1-31 jan-dec mon-fri',
            (new CronExpressionValidator())->validate('5,15 0-23/2 1-31 JAN-DEC MON-FRI')
        );
    }

    #[DataProvider('invalidExpressions')]
    public function testRejectsInvalidExpression(string $expression): void
    {
        $this->expectException(LocalizedException::class);
        (new CronExpressionValidator())->validate($expression);
    }

    /** @return array<string, array{string}> */
    public static function invalidExpressions(): array
    {
        return [
            'too short' => ['*/20 * * *'],
            'unsupported character' => ['*/20 * * * ?'],
            'minute outside range' => ['60 * * * *'],
            'hour outside range' => ['0 24 * * *'],
            'day outside range' => ['0 0 0 * *'],
            'month outside range' => ['0 0 1 13 *'],
            'weekday unsupported by Magento' => ['0 0 * * 7'],
            'zero step' => ['*/0 * * * *'],
            'reversed range' => ['10-5 * * * *'],
            'name in numeric field' => ['mon * * * *'],
            'empty list item' => ['1, * * * *'],
            'multiple steps' => ['*/2/3 * * * *'],
        ];
    }
}
