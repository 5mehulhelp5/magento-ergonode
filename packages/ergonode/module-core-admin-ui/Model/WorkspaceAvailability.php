<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Model\Data\ReadinessContext;
use Magento\Framework\Exception\LocalizedException;

class WorkspaceAvailability
{
    public function __construct(private readonly ReadinessCheckInterface $connectionCheck)
    {
    }

    /** @return string[] */
    public function getProblems(): array
    {
        try {
            $issues = $this->connectionCheck->check(new ReadinessContext(
                ReadinessContextInterface::OPERATION_OVERVIEW
            ));
        } catch (LocalizedException $exception) {
            return [$exception->getMessage()];
        }

        $problems = [];
        foreach ($issues as $issue) {
            if ($issue->getSeverity() === ReadinessIssueInterface::SEVERITY_BLOCKER) {
                $problems[] = $issue->getMessage();
            }
        }

        return $problems;
    }
}
