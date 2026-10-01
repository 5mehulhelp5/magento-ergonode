<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\EndpointResolver;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Magento\Backend\Model\Auth\Session;

class CategoryPublicationDetails
{
    private const string SESSION_KEY = 'ergonode_category_publication_details';

    public function __construct(private readonly Session $session, private readonly EndpointResolver $endpointResolver)
    {
    }

    /** @param array<string, CategorySynchronizationResultInterface> $results */
    public function remember(int $treeId, array $results): void
    {
        $categories = $this->get($treeId);
        foreach ($results as $code => $result) {
            unset($categories[$code]);
            if ($result->getStatus() !== CategorySynchronizationResultInterface::STATUS_SUCCESS
                || $result->getReferenceStatus() !== CategorySynchronizationResultInterface::REFERENCE_PRESENT
            ) {
                continue;
            }
            foreach ($result->getResults() as $mutation) {
                $data = $mutation->getData();
                $category = is_array($data) ? ($data['category'] ?? null) : null;
                if ($mutation->getStatus() === MutationResultInterface::STATUS_SUCCESS
                    && is_array($category) && ($category['code'] ?? null) === (string)$code
                    && is_array($category['name'] ?? null)
                ) {
                    $categories[$code] = ['code' => (string)$code, 'name' => $category['name']];
                }
            }
        }
        $this->store($treeId, $categories);
    }

    /** @return array<string, array{code: string, name: array<int, array<string, mixed>>}> */
    public function get(int $treeId): array
    {
        $stored = $this->session->getData(self::SESSION_KEY);

        return is_array($stored) && ($stored['scope'] ?? '') === $this->scope($treeId)
            ? (array)($stored['categories'] ?? []) : [];
    }

    /** @param string[] $codes */
    public function clear(int $treeId, array $codes): void
    {
        $categories = $this->get($treeId);
        if ($categories === []) {
            return;
        }
        $this->store($treeId, array_diff_key($categories, array_fill_keys($codes, true)));
    }

    /** @param array<string, array{code: string, name: array<int, array<string, mixed>>}> $categories */
    private function store(int $treeId, array $categories): void
    {
        $this->session->setData(self::SESSION_KEY, ['scope' => $this->scope($treeId), 'categories' => $categories]);
    }

    private function scope(int $treeId): string
    {
        return hash('sha256', $this->endpointResolver->loginUrl() . ':' . $treeId);
    }
}
