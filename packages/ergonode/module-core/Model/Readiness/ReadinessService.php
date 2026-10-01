<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Readiness;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\Data\ReadinessReportInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessEvaluatorInterface;
use Ergonode\Core\Api\ReadinessGuardInterface;
use Ergonode\Core\Exception\ReadinessGuardException;
use Ergonode\Core\Model\Data\ReadinessContext;
use Ergonode\Core\Model\Data\ReadinessReport;
use InvalidArgumentException;

class ReadinessService implements ReadinessEvaluatorInterface, ReadinessGuardInterface
{
    /** @param ReadinessCheckInterface[] $checks */
    public function __construct(private readonly array $checks = [])
    {
        foreach ($checks as $check) {
            if (!$check instanceof ReadinessCheckInterface) {
                throw new InvalidArgumentException('Readiness service accepts only readiness checks.');
            }
        }
    }

    public function evaluate(string $operation, array $entityIdentifiers = []): ReadinessReportInterface
    {
        $context = new ReadinessContext($operation, $entityIdentifiers);
        $issues = [];
        $checkedDomains = [];
        foreach ($this->checks as $check) {
            if (!$check->supports($operation)) {
                continue;
            }
            $checkedDomains[] = $check->getDomain();
            foreach ($check->check($context) as $issue) {
                if (!$issue instanceof ReadinessIssueInterface) {
                    throw new InvalidArgumentException(sprintf(
                        'Readiness check "%s" returned an invalid issue.',
                        $check->getCode()
                    ));
                }
                $issues[] = $issue;
            }
        }
        usort($issues, [$this, 'compareIssues']);
        $checkedDomains = array_values(array_unique($checkedDomains));
        sort($checkedDomains);

        return new ReadinessReport($issues, $checkedDomains);
    }

    public function assertReady(string $operation, array $entityIdentifiers = []): void
    {
        $report = $this->evaluate($operation, $entityIdentifiers);
        if ($report->hasBlockers()) {
            throw new ReadinessGuardException($report);
        }
    }

    private function compareIssues(ReadinessIssueInterface $left, ReadinessIssueInterface $right): int
    {
        $severityOrder = [
            ReadinessIssueInterface::SEVERITY_BLOCKER => 0,
            ReadinessIssueInterface::SEVERITY_WARNING => 1,
        ];

        return [$severityOrder[$left->getSeverity()], $left->getDomain(), $left->getCode()]
            <=> [$severityOrder[$right->getSeverity()], $right->getDomain(), $right->getCode()];
    }
}
