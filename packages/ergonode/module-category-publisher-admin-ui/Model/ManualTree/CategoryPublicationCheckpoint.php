<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\EndpointResolver;
use Magento\Backend\Model\Auth\Session;

class CategoryPublicationCheckpoint
{
    private const string SESSION_KEY = 'ergonode_category_publication';

    public function __construct(private readonly Session $session, private readonly EndpointResolver $endpointResolver)
    {
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, bool> */
    public function get(int $treeId, array $items, string $operation = ''): array
    {
        $checkpoint = $this->session->getData(self::SESSION_KEY . $operation);
        if (!is_array($checkpoint) || ($checkpoint['fingerprint'] ?? '') !== $this->fingerprint($treeId, $items)) {
            return [];
        }

        return array_fill_keys((array)($checkpoint['created_codes'] ?? []), true);
    }

    /** @param array<int, array<string, mixed>> $items @param string[] $createdCodes */
    public function save(int $treeId, array $items, array $createdCodes, string $operation = ''): void
    {
        if ($createdCodes !== []) {
            $this->session->setData(self::SESSION_KEY . $operation, [
                'fingerprint' => $this->fingerprint($treeId, $items),
                'created_codes' => $createdCodes,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    public function clear(int $treeId, array $items, string $operation = ''): void
    {
        if ($this->get($treeId, $items, $operation) !== []) {
            $this->session->unsetData(self::SESSION_KEY . $operation);
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function fingerprint(int $treeId, array $items): string
    {
        return hash('sha256', $this->endpointResolver->loginUrl() . ':' . $treeId . ':' . json_encode($items));
    }
}
