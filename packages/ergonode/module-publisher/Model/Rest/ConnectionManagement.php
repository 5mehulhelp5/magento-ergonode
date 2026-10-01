<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionManagementInterface;
use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\AuthenticationException;

class ConnectionManagement implements ConnectionManagementInterface
{
    public function __construct(
        private readonly ConnectionContext $context,
        private readonly ConnectionStorageInterface $storage,
        private readonly ConnectionLock $lock,
        private readonly Transport $transport,
        private readonly TokenPair $tokenPair,
        private readonly AccessTokenProvider $tokens
    ) {
    }

    public function login(string $email, string $password): void
    {
        $this->context->assertAvailable();
        if (trim($email) === '' || $password === '') {
            throw new LocalizedException(__('Ergonode email and password are required.'));
        }
        $profile = $this->context->profile();
        $origin = $this->context->origin();
        $this->lock->execute($profile, function () use ($profile, $origin, $email, $password): void {
            $pair = $this->tokenPair->read($this->transport->request(
                $profile,
                $origin,
                'POST',
                '/api/v1/login',
                ['username' => trim($email), 'password' => $password]
            ));
            $this->storage->save($profile, $pair + [
                'origin' => $origin, 'email' => trim($email), 'generation' => bin2hex(random_bytes(16)),
            ]);
        });
    }

    public function disconnect(): void
    {
        $profile = $this->context->profile();
        $this->lock->execute($profile, fn () => $this->storage->delete($profile));
    }

    public function status(): array
    {
        $connection = $this->storage->get($this->context->profile());
        $ready = $connection !== null && $connection['origin'] === $this->context->origin()
            && $connection['token'] !== '' && $connection['refresh_token'] !== '';
        if ($ready) {
            $this->context->assertAvailable();
            try {
                $connection = $this->tokens->get($this->context->profile(), $this->context->origin());
            } catch (AuthenticationException) {
                $ready = false;
            }
        }
        return ['authenticated' => $ready, 'email' => $ready ? (string)$connection['email'] : ''];
    }
}
