<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Model\Data\ReadinessContext;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Language\Model\Readiness\DefaultStoreMappingCheck;
use PHPUnit\Framework\TestCase;

class DefaultStoreMappingCheckTest extends TestCase
{
    public function testBlocksWhenDefaultValuesHaveNoLanguageMapping(): void
    {
        $mappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $mappingProvider->method('getAdminLanguageCode')->willReturn(null);
        $check = new DefaultStoreMappingCheck($mappingProvider, new ReadinessIssueFactory());

        $issues = $check->check(new ReadinessContext(ReadinessContextInterface::OPERATION_OVERVIEW));

        self::assertCount(1, $issues);
        self::assertSame('language.default_store_mapping_missing', $issues[0]->getCode());
        self::assertSame('blocker', $issues[0]->getSeverity());
    }
}
