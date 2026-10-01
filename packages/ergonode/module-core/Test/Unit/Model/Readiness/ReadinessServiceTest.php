<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Exception\ReadinessGuardException;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use Ergonode\Core\Model\Readiness\ReadinessService;
use PHPUnit\Framework\TestCase;

class ReadinessServiceTest extends TestCase
{
    public function testAggregatesSupportedChecksAndSortsBlockersBeforeWarnings(): void
    {
        $factory = new ReadinessIssueFactory();
        $warning = $factory->create(
            'categories.empty',
            'categories',
            ReadinessIssueInterface::SEVERITY_WARNING,
            'No categories.'
        );
        $blocker = $factory->create(
            'languages.default',
            'languages',
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            'Default language missing.'
        );
        $service = new ReadinessService([
            $this->check('categories', [$warning]),
            $this->check('languages', [$blocker]),
            $this->check('ignored', [], false),
        ]);

        $report = $service->evaluate(ReadinessContextInterface::OPERATION_OVERVIEW);

        self::assertTrue($report->hasBlockers());
        self::assertSame('blocked', $report->getStatus());
        self::assertSame(['categories', 'languages'], $report->getCheckedDomains());
        self::assertSame([$blocker, $warning], $report->getIssues());
    }

    public function testGuardSummarizesBlockers(): void
    {
        $issue = (new ReadinessIssueFactory())->create(
            'templates.empty',
            'templates',
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            'Template mapping missing.'
        );
        $service = new ReadinessService([$this->check('templates', [$issue])]);

        try {
            $service->assertReady(ReadinessContextInterface::OPERATION_PUBLISH_PRODUCTS);
            self::fail('The readiness guard should throw.');
        } catch (ReadinessGuardException $exception) {
            self::assertStringContainsString('Template mapping missing.', $exception->getMessage());
        }
    }

    /** @param ReadinessIssueInterface[] $issues */
    private function check(string $domain, array $issues, bool $supported = true): ReadinessCheckInterface
    {
        return new class ($domain, $issues, $supported) implements ReadinessCheckInterface {
            /** @param ReadinessIssueInterface[] $issues */
            public function __construct(
                private readonly string $domain,
                private readonly array $issues,
                private readonly bool $supported
            ) {
            }

            public function getCode(): string
            {
                return $this->domain . '.check';
            }

            public function getDomain(): string
            {
                return $this->domain;
            }

            public function supports(string $operation): bool
            {
                return $this->supported;
            }

            public function check(ReadinessContextInterface $context): array
            {
                return $this->issues;
            }
        };
    }
}
