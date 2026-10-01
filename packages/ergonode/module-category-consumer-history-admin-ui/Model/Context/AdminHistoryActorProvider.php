<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Model\Context;

use Ergonode\CategoryConsumerHistory\Api\HistoryActorProviderInterface;
use Magento\Backend\Model\Auth\Session;

class AdminHistoryActorProvider implements HistoryActorProviderInterface
{
    public function __construct(private readonly Session $authSession)
    {
    }

    public function getActor(): array
    {
        $user = $this->authSession->getUser();
        if (!$user || !$user->getId()) {
            return ['actor_id' => null, 'actor_name' => null];
        }

        $name = trim((string)$user->getFirstName() . ' ' . (string)$user->getLastName());

        return [
            'actor_id' => (int)$user->getId(),
            'actor_name' => $name !== '' ? $name : (string)$user->getUserName(),
        ];
    }
}
