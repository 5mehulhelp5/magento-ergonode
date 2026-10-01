<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\AttributePublisher\Model\Sync\MutationFailureMessageFormatter;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use PHPUnit\Framework\TestCase;

class MutationFailureMessageFormatterTest extends TestCase
{
    public function testFormatsTheFirstErrorCodeAndMessage(): void
    {
        $result = $this->createStub(MutationResultInterface::class);
        $result->method('getErrors')->willReturn([[
            'message' => 'Attribute already exists.',
            'extensions' => ['code' => 'bad_user_input'],
        ]]);

        self::assertSame(
            '[BAD_USER_INPUT] Attribute already exists.',
            (new MutationFailureMessageFormatter())->format($result, 'Attribute')
        );
    }

    public function testFallsBackToTheSubjectAndMutationStatus(): void
    {
        $result = $this->createStub(MutationResultInterface::class);
        $result->method('getErrors')->willReturn([]);
        $result->method('getStatus')->willReturn(MutationResultInterface::STATUS_PERMANENT_FAILURE);

        self::assertSame(
            'Option mutation failed with status permanent_failure.',
            (new MutationFailureMessageFormatter())->format($result, 'Option')
        );
    }
}
