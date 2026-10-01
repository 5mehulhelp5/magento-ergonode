<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml;

use function array_fill_keys;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\Data\ReadinessReportInterface;
use Ergonode\Core\Api\ReadinessEvaluatorInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

use Magento\Framework\Phrase;

class Readiness extends Template
{
    private ?ReadinessReportInterface $report = null;

    /** @var array<string, string> */
    private array $domainLabels;

    /** @var array<string, string> */
    private array $remediationRoutes;

    /**
     * @param array<string, string> $domainLabels
     * @param array<string, string> $remediationRoutes
     */
    public function __construct(
        Context $context,
        private readonly ReadinessEvaluatorInterface $readinessEvaluator,
        array $domainLabels = [],
        array $remediationRoutes = [],
        array $data = []
    ) {
        $this->domainLabels = $domainLabels;
        $this->remediationRoutes = $remediationRoutes;
        parent::__construct($context, $data);
    }

    public function getReport(): ReadinessReportInterface
    {
        return $this->report ??= $this->readinessEvaluator->evaluate(
            ReadinessContextInterface::OPERATION_OVERVIEW
        );
    }

    /** @return array<string, ReadinessIssueInterface[]> */
    public function getIssuesByDomain(): array
    {
        $domains = array_fill_keys($this->getReport()->getCheckedDomains(), []);
        foreach ($this->getReport()->getIssues() as $issue) {
            $domains[$issue->getDomain()][] = $issue;
        }

        return $domains;
    }

    public function getDomainLabel(string $domain): Phrase
    {
        return __($this->domainLabels[$domain] ?? 'Other');
    }

    public function getStatusLabel(string $status): Phrase
    {
        return match ($status) {
            ReadinessReportInterface::STATUS_READY => __('Ready'),
            ReadinessReportInterface::STATUS_WARNING => __('Warning'),
            default => __('Blocked'),
        };
    }

    public function getSummaryMessage(): Phrase
    {
        return match ($this->getReport()->getStatus()) {
            ReadinessReportInterface::STATUS_READY => __(
                'The configured Ergonode integration capabilities are ready.'
            ),
            ReadinessReportInterface::STATUS_WARNING => __(
                'Required synchronization can run; review optional capabilities and non-blocking issues.'
            ),
            default => __('Resolve the blocking requirements before starting synchronization.'),
        };
    }

    public function getRemediationUrl(ReadinessIssueInterface $issue): ?string
    {
        $route = $this->remediationRoutes[$issue->getRemediation()] ?? null;

        return $route !== null ? $this->getUrl($route) : null;
    }
}
