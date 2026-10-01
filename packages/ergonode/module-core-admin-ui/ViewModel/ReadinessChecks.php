<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\ViewModel;

use function array_filter;
use function array_keys;
use function array_merge;
use function array_values;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;

use Ergonode\Core\Api\Data\ReadinessReportInterface;

use function in_array;

use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class ReadinessChecks implements ArgumentInterface
{
    private const string STATUS_NOT_CHECKED = 'not-checked';

    private const string STATUS_REQUIRES_LOGIN = 'requires-login';

    /**
     * @param array<string, array<int, array{
     *     label: string,
     *     issue_codes: string[],
     *     blocked_by?: string[],
     *     group?: string
     * }>> $definitions
     * @param array<string, int> $domainSortOrders
     */
    public function __construct(
        private readonly array $definitions = [],
        private readonly array $domainSortOrders = []
    ) {
    }

    /**
     * @param string $domain
     * @param ReadinessIssueInterface[] $issues
     * @return array<int, array{
     *     label: Phrase,
     *     status: string,
     *     issues: ReadinessIssueInterface[],
     *     group: string
     * }>
     */
    public function getForDomain(string $domain, array $issues): array
    {
        $checks = [];
        $matchedIssueCodes = [];
        $definitions = $this->getDefinitions($domain);
        if ($definitions === []) {
            return [[
                'label' => __('Readiness requirements for this area are satisfied.'),
                'status' => $this->getStatus($issues),
                'issues' => $issues,
                'group' => 'additional',
            ]];
        }

        foreach ($definitions as $definition) {
            $checkIssues = $this->getIssuesByCodes($issues, $definition['issue_codes']);
            $blockedByIssues = $this->getIssuesByCodes($issues, $definition['blocked_by'] ?? []);
            $matchedIssueCodes = array_merge($matchedIssueCodes, $definition['issue_codes']);
            $checks[] = [
                'label' => $definition['label'],
                'status' => $blockedByIssues === []
                    ? $this->getStatus($checkIssues)
                    : self::STATUS_NOT_CHECKED,
                'issues' => $checkIssues,
                'group' => $definition['group'] ?? $domain,
            ];
        }

        foreach ($issues as $issue) {
            if (in_array($issue->getCode(), $matchedIssueCodes, true)) {
                continue;
            }
            $checks[] = [
                'label' => __('Additional readiness requirement'),
                'status' => $this->getStatus([$issue]),
                'issues' => [$issue],
                'group' => 'additional',
            ];
        }

        return $checks;
    }

    /**
     * @param array<string, ReadinessIssueInterface[]> $issuesByDomain
     * @return array<int, array{
     *     code: string,
     *     label: Phrase,
     *     description: Phrase,
     *     kind: Phrase,
     *     domains: array<int, array{
     *         code: string,
     *         checks: array<int, array{
     *             label: Phrase,
     *             status: string,
     *             issues: ReadinessIssueInterface[],
     *             group: string
     *         }>
     *     }>
     * }>
     */
    public function getFlows(array $issuesByDomain): array
    {
        $checksByDomain = [];
        foreach ($issuesByDomain as $domain => $issues) {
            $checksByDomain[$domain] = $this->getChecksForFlow($domain, $issues);
        }

        $flows = [];
        foreach ($this->getFlowDefinitions() as $code => $definition) {
            $flows[] = [
                'code' => $code,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'kind' => $definition['kind'],
                'domains' => $this->getDomainsForFlow($checksByDomain, $definition),
            ];
        }

        return $flows;
    }

    /** @param string $status */
    public function getStatusLabel(string $status): Phrase
    {
        return match ($status) {
            self::STATUS_NOT_CHECKED => __('Not checked'),
            self::STATUS_REQUIRES_LOGIN => __('Requires login'),
            ReadinessReportInterface::STATUS_READY => __('Passed'),
            ReadinessReportInterface::STATUS_WARNING => __('Warning'),
            default => __('Failed'),
        };
    }

    /**
     * @return array<int, array{label: Phrase, issue_codes: string[], blocked_by?: string[], group?: string}>
     */
    private function getDefinitions(string $domain): array
    {
        return array_map(
            static fn (array $definition): array => [
                ...$definition,
                'label' => __($definition['label']),
            ],
            $this->definitions[$domain] ?? []
        );
    }

    /**
     * @return array<string, array{
     *     label: Phrase,
     *     description: Phrase,
     *     kind: Phrase,
     *     groups: string[],
     * }>
     */
    private function getFlowDefinitions(): array
    {
        return [
            'read' => [
                'label' => __('Receive data from Ergonode'),
                'description' => __(
                    'Required for importing and updating Magento data from Ergonode. '
                        . 'This is sufficient for most integrations.'
                ),
                'kind' => __('Required'),
                'groups' => ['read', 'additional'],
            ],
            'write' => [
                'label' => __('Send data to Ergonode'),
                'description' => __(
                    'Optional add-on for clients that start with catalog data in Magento '
                        . 'and want to send it to Ergonode.'
                ),
                'kind' => __('Optional'),
                'groups' => ['write', 'rest'],
            ],
        ];
    }

    /**
     * @param ReadinessIssueInterface[] $issues
     * @return array<int, array{
     *     label: Phrase,
     *     status: string,
     *     issues: ReadinessIssueInterface[],
     *     group: string
     * }>
     */
    private function getChecksForFlow(string $domain, array $issues): array
    {
        $checks = $this->getForDomain($domain, $issues);
        if ($domain !== 'connection') {
            return $checks;
        }

        return array_merge($checks, [
            [
                'label' => __('Load the signed-in account privileges from the Ergonode REST profile.'),
                'status' => self::STATUS_REQUIRES_LOGIN,
                'issues' => [],
                'group' => 'rest',
            ],
            [
                'label' => __(
                    'Compare them with the permissions required by the selected operation.'
                ),
                'status' => self::STATUS_REQUIRES_LOGIN,
                'issues' => [],
                'group' => 'rest',
            ],
        ]);
    }

    /**
     * @param array<string, array<int, array{
     *     label: Phrase,
     *     status: string,
     *     issues: ReadinessIssueInterface[],
     *     group: string
     * }>> $checksByDomain
     * @param array{groups: string[]} $definition
     * @return array<int, array{
     *     code: string,
     *     checks: array<int, array{
     *         label: Phrase,
     *         status: string,
     *         issues: ReadinessIssueInterface[],
     *         group: string
     *     }>
     * }>
     */
    private function getDomainsForFlow(array $checksByDomain, array $definition): array
    {
        $domains = [];
        $domainsByOrder = array_keys($checksByDomain);
        usort(
            $domainsByOrder,
            fn (string $left, string $right): int => ($this->domainSortOrders[$left] ?? 1000)
                <=> ($this->domainSortOrders[$right] ?? 1000)
        );
        foreach ($domainsByOrder as $domain) {
            $checks = array_values(array_filter(
                $checksByDomain[$domain],
                static fn (array $check): bool => in_array($check['group'], $definition['groups'], true)
            ));
            if ($checks !== []) {
                $domains[] = ['code' => $domain, 'checks' => $checks];
            }
        }

        return $domains;
    }

    /**
     * @param ReadinessIssueInterface[] $issues
     */
    private function getStatus(array $issues): string
    {
        foreach ($issues as $issue) {
            if ($issue->getSeverity() === ReadinessIssueInterface::SEVERITY_BLOCKER) {
                return ReadinessReportInterface::STATUS_BLOCKED;
            }
        }

        return $issues === []
            ? ReadinessReportInterface::STATUS_READY
            : ReadinessReportInterface::STATUS_WARNING;
    }

    /**
     * @param ReadinessIssueInterface[] $issues
     * @param string[] $codes
     * @return ReadinessIssueInterface[]
     */
    private function getIssuesByCodes(array $issues, array $codes): array
    {
        return array_values(array_filter(
            $issues,
            static fn (ReadinessIssueInterface $issue): bool => in_array($issue->getCode(), $codes, true)
        ));
    }
}
