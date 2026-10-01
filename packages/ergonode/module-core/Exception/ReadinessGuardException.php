<?php

declare(strict_types=1);

namespace Ergonode\Core\Exception;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\Data\ReadinessReportInterface;
use Magento\Framework\Exception\LocalizedException;

class ReadinessGuardException extends LocalizedException
{
    public function __construct(ReadinessReportInterface $report)
    {
        $messages = [];
        foreach ($report->getIssues() as $issue) {
            if ($issue->getSeverity() === ReadinessIssueInterface::SEVERITY_BLOCKER) {
                $messages[] = $issue->getMessage();
            }
        }

        parent::__construct(__(
            'Ergonode synchronization cannot start: %1',
            implode(' ', $messages)
        ));
    }
}
