<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Import;

use Ergonode\TemplateConsumer\Api\TemplateSynchronizationContributorInterface;
use Ergonode\TemplateConsumer\Model\Import\TemplateSynchronizationProcessor;
use Ergonode\TemplateConsumer\Model\Mapping\TemplateAttributeSetAutoMatcher;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetSynchronizer;
use PHPUnit\Framework\TestCase;

class TemplateSynchronizationProcessorTest extends TestCase
{
    public function testResolvesSetsBeforeRunningOptionalStructureSynchronization(): void
    {
        $order = [];
        $matcher = $this->createMock(TemplateAttributeSetAutoMatcher::class);
        $matcher->expects(self::once())->method('match')->with(['active'])
            ->willReturnCallback(static function () use (&$order): array {
                $order[] = 'mapping';
                return [];
            });
        $sets = $this->createMock(TemplateAttributeSetSynchronizer::class);
        $sets->expects(self::once())->method('sync')->with(['active'])
            ->willReturnCallback(static function () use (&$order): void {
                $order[] = 'sets';
            });
        $contributor = $this->createMock(TemplateSynchronizationContributorInterface::class);
        $contributor->expects(self::once())->method('execute')->with(['active'], ['deleted'])
            ->willReturnCallback(static function () use (&$order): void {
                $order[] = 'structure';
            });
        (new TemplateSynchronizationProcessor($matcher, $sets, [$contributor]))->execute(['active'], ['deleted']);
        self::assertSame(['mapping', 'sets', 'structure'], $order);
    }
}
