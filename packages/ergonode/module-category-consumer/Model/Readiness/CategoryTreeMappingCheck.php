<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Readiness;

use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;
use Ergonode\Core\Api\ReadinessCheckInterface;
use Ergonode\Core\Api\ReadinessIssueFactoryInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryTreeMappingCheck implements ReadinessCheckInterface
{
    public function __construct(
        private readonly CategoryTreeReadinessProviderInterface $treeProvider,
        private readonly ReadinessIssueFactoryInterface $issueFactory,
        private readonly CategoryCreationConfigurationProviderInterface $creationConfigurationProvider
    ) {
    }

    public function getCode(): string
    {
        return 'categories.tree_mapping';
    }

    public function getDomain(): string
    {
        return 'categories';
    }

    public function supports(string $operation): bool
    {
        return $operation === ReadinessContextInterface::OPERATION_OVERVIEW;
    }

    public function check(ReadinessContextInterface $context): array
    {
        $trees = $this->treeProvider->getActiveTrees();
        if ($trees === []) {
            return [$this->issueFactory->create(
                'categories.active_tree_mapping_missing',
                $this->getDomain(),
                ReadinessIssueInterface::SEVERITY_BLOCKER,
                (string)__('Connect at least one active Ergonode category tree to a Magento root category.'),
                remediation: 'categories'
            )];
        }

        $issues = [];
        $invalidRoots = [];
        foreach ($trees as $tree) {
            if (!$tree['root_exists']) {
                $invalidRoots[] = (string)__(
                    'Tree "%1" references missing Magento root category ID %2.',
                    $tree['tree_code'],
                    $tree['root_category_id']
                );
            }
        }
        if ($invalidRoots !== []) {
            $issues[] = $this->issueFactory->create(
                'categories.root_category_missing',
                $this->getDomain(),
                ReadinessIssueInterface::SEVERITY_BLOCKER,
                (string)__('An active category-tree mapping references a missing Magento root category.'),
                $invalidRoots,
                'categories'
            );
        }
        $issues = [...$issues, ...$this->creationAttributeIssues()];

        return $issues;
    }

    /** @return ReadinessIssueInterface[] */
    private function creationAttributeIssues(): array
    {
        try {
            $this->creationConfigurationProvider->get();
        } catch (LocalizedException $exception) {
            return [$this->issueFactory->create(
                'categories.category_mapping_missing',
                $this->getDomain(),
                ReadinessIssueInterface::SEVERITY_BLOCKER,
                $exception->getMessage(),
                remediation: 'categories'
            )];
        }

        return [];
    }
}
