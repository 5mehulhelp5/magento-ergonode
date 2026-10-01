<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\ViewModel;

use function array_column;

use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Model\Readiness\ReadinessIssueFactory;
use Ergonode\CoreAdminUi\ViewModel\ReadinessChecks;

use PHPUnit\Framework\TestCase;

class ReadinessChecksTest extends TestCase
{
    /** @var ReadinessChecks */
    private ReadinessChecks $viewModel;

    /** Prepare the readiness checks view model. */
    protected function setUp(): void
    {
        $this->viewModel = new ReadinessChecks([
            'connection' => [
                [
                    'label' => 'Ergonode GraphQL URL is configured.',
                    'issue_codes' => ['connection.graphql_url_missing'],
                    'group' => 'read',
                ],
                [
                    'label' => 'Ergonode read operations are enabled.',
                    'issue_codes' => ['connection.mode_unavailable'],
                    'group' => 'read',
                ],
                [
                    'label' => 'Ergonode read API key is configured.',
                    'issue_codes' => ['connection.api_key_missing'],
                    'group' => 'read',
                ],
                [
                    'label' => 'Ergonode write operations are enabled.',
                    'issue_codes' => ['products.write_operations_disabled'],
                    'group' => 'write',
                ],
                [
                    'label' => 'Ergonode write API key is configured.',
                    'issue_codes' => ['products.write_api_key_missing'],
                    'group' => 'write',
                ],
            ],
            'languages' => [[
                'label' => 'Magento Default Values (store ID 0) is mapped to an active Ergonode language.',
                'issue_codes' => ['language.default_store_mapping_missing'],
                'group' => 'read',
            ]],
            'attributes' => [[
                'label' => 'Every required Magento product attribute has an Ergonode mapping.',
                'issue_codes' => ['attributes.required_mapping_missing'],
                'group' => 'write',
            ]],
            'categories' => [
                [
                    'label' => 'An active Ergonode category tree is connected to a Magento root category.',
                    'issue_codes' => ['categories.active_tree_mapping_missing'],
                    'group' => 'read',
                ],
                [
                    'label' => 'Mapped Magento root categories exist.',
                    'issue_codes' => ['categories.root_category_missing'],
                    'blocked_by' => ['categories.active_tree_mapping_missing'],
                    'group' => 'read',
                ],
                [
                    'label' => 'New categories have required attribute values.',
                    'issue_codes' => ['categories.category_mapping_missing'],
                    'blocked_by' => ['categories.active_tree_mapping_missing'],
                    'group' => 'write',
                ],
            ],
        ], [
            'connection' => 10,
            'languages' => 20,
            'attributes' => 20,
            'categories' => 40,
        ]);
    }

    /** Verify that connection requirements expose independent statuses. */
    public function testReturnsAnIndependentStatusForEveryConnectionRequirement(): void
    {
        $issue = (new ReadinessIssueFactory())->create(
            'connection.mode_unavailable',
            'connection',
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            'Enable Ergonode read operations.'
        );

        $checks = $this->viewModel->getForDomain('connection', [$issue]);

        self::assertCount(5, $checks);
        self::assertSame(
            ['ready', 'blocked', 'ready', 'ready', 'ready'],
            array_column($checks, 'status')
        );
        self::assertSame(
            ['read', 'read', 'read', 'write', 'write'],
            array_column($checks, 'group')
        );
        self::assertSame([$issue], $checks[1]['issues']);
    }

    /** Verify that readiness requirements are ordered by integration direction. */
    public function testGroupsRequirementsIntoReceivingAndSendingFlows(): void
    {
        $flows = $this->viewModel->getFlows([
            'attributes' => [],
            'categories' => [],
            'connection' => [],
            'languages' => [],
        ]);

        self::assertSame(['read', 'write'], array_column($flows, 'code'));
        self::assertSame(
            ['connection', 'languages', 'categories'],
            array_column($flows[0]['domains'], 'code')
        );
        self::assertSame(
            ['connection', 'attributes', 'categories'],
            array_column($flows[1]['domains'], 'code')
        );
        self::assertSame([3, 1, 2], array_map(
            static fn (array $domain): int => count($domain['checks']),
            $flows[0]['domains']
        ));
        self::assertSame([4, 1, 1], array_map(
            static fn (array $domain): int => count($domain['checks']),
            $flows[1]['domains']
        ));
        self::assertSame(
            ['requires-login', 'requires-login'],
            array_slice(array_column($flows[1]['domains'][0]['checks'], 'status'), -2)
        );
    }

    /** Verify that a category creation configuration warning stays visible. */
    public function testPreservesWarningStatusForCategoryCreationConfiguration(): void
    {
        $issue = (new ReadinessIssueFactory())->create(
            'categories.category_mapping_missing',
            'categories',
            ReadinessIssueInterface::SEVERITY_WARNING,
            'No category is mapped.'
        );

        $checks = $this->viewModel->getForDomain('categories', [$issue]);

        self::assertCount(3, $checks);
        self::assertSame(['ready', 'ready', 'warning'], array_column($checks, 'status'));
        self::assertSame(['read', 'read', 'write'], array_column($checks, 'group'));
        self::assertSame([$issue], $checks[2]['issues']);
    }

    /** Verify that a future issue cannot disappear from the readiness view. */
    public function testKeepsUnknownIssuesVisible(): void
    {
        $issue = (new ReadinessIssueFactory())->create(
            'connection.new_requirement',
            'connection',
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            'A new requirement failed.'
        );

        $checks = $this->viewModel->getForDomain('connection', [$issue]);

        self::assertCount(6, $checks);
        self::assertSame('blocked', $checks[5]['status']);
        self::assertSame('additional', $checks[5]['group']);
        self::assertSame([$issue], $checks[5]['issues']);

        $flows = $this->viewModel->getFlows(['connection' => [$issue]]);

        self::assertCount(4, $flows[0]['domains'][0]['checks']);
        self::assertSame([$issue], $flows[0]['domains'][0]['checks'][3]['issues']);
    }

    /** Verify that skipped dependent checks are not reported as passed. */
    public function testDoesNotReportDependentCategoryChecksAsPassed(): void
    {
        $issue = (new ReadinessIssueFactory())->create(
            'categories.active_tree_mapping_missing',
            'categories',
            ReadinessIssueInterface::SEVERITY_BLOCKER,
            'Connect an active category tree.'
        );

        $checks = $this->viewModel->getForDomain('categories', [$issue]);

        self::assertSame(['blocked', 'not-checked', 'not-checked'], array_column($checks, 'status'));
    }
}
