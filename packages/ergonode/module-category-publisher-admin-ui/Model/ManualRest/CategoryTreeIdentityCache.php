<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualRest;

use Magento\Backend\Model\Auth\Session;

class CategoryTreeIdentityCache
{
    private const string SESSION_KEY = 'ergonode_category_tree_identities';

    public function __construct(private readonly Session $session, private readonly EndpointResolver $endpointResolver)
    {
    }

    public function get(string $code): ?string
    {
        return $this->identities()[$code] ?? null;
    }

    public function save(string $code, string $id): void
    {
        $this->store(array_replace($this->identities(), [$code => $id]));
    }

    public function remove(string $code): void
    {
        $identities = $this->identities();
        unset($identities[$code]);
        $this->store($identities);
    }

    /** @return array<string, string> */
    private function identities(): array
    {
        $stored = $this->session->getData(self::SESSION_KEY);

        return is_array($stored) && ($stored['origin'] ?? '') === $this->endpointResolver->loginUrl()
            ? (array)($stored['identities'] ?? []) : [];
    }

    /** @param array<string, string> $identities */
    private function store(array $identities): void
    {
        $this->session->setData(self::SESSION_KEY, [
            'origin' => $this->endpointResolver->loginUrl(),
            'identities' => $identities,
        ]);
    }
}
