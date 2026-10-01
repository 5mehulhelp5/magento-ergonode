<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Rest;

use Ergonode\Publisher\Api\Exception\RestRequestException as RequestException;
use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Magento\Framework\Exception\AuthenticationException;
use Psr\Log\LoggerInterface;

class AccessTokenProvider
{
    public function __construct(
        private readonly ConnectionStorageInterface $storage,
        private readonly ConnectionLock $lock,
        private readonly Transport $transport,
        private readonly TokenPair $tokenPair,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @return array<string, mixed> */
    public function get(string $profile, string $origin, ?string $rejectedToken = null): array
    {
        $connection = $this->requireConnection($profile, $origin);
        if ($this->usable($connection, $rejectedToken)) {
            return $connection;
        }
        return $this->lock->execute($profile, function () use ($profile, $origin, $rejectedToken): array {
            $connection = $this->requireConnection($profile, $origin);
            if ($this->usable($connection, $rejectedToken)) {
                return $connection;
            }
            try {
                $pair = $this->tokenPair->read($this->transport->request(
                    $profile,
                    $origin,
                    'POST',
                    '/api/v1/token/refresh',
                    ['refresh_token' => $connection['refresh_token']]
                ));
            } catch (RequestException $exception) {
                if (in_array($exception->getStatusCode(), [400, 401, 403], true)) {
                    $this->logAuthenticationFailure($profile, 'refresh_rejected', $exception->getStatusCode());
                    $this->storage->delete($profile);
                    throw new AuthenticationException(__('The Ergonode REST connection expired. Log in again.'));
                }
                throw $exception;
            }
            $connection = array_replace($connection, $pair);
            $this->storage->save($profile, $connection);
            return $connection;
        });
    }

    /** @return array<string, mixed> */
    private function requireConnection(string $profile, string $origin): array
    {
        $connection = $this->storage->get($profile);
        if ($connection === null) {
            $this->logAuthenticationFailure($profile, 'connection_missing');
            throw new AuthenticationException(__('Log in to Ergonode to continue this operation.'));
        }
        if ($connection['origin'] !== $origin) {
            $this->logAuthenticationFailure($profile, 'origin_mismatch');
            throw new AuthenticationException(__('Log in to Ergonode to continue this operation.'));
        }
        if ($connection['token'] === '') {
            $this->logAuthenticationFailure($profile, 'access_token_missing');
            throw new AuthenticationException(__('Log in to Ergonode to continue this operation.'));
        }
        if ($connection['refresh_token'] === '') {
            $this->logAuthenticationFailure($profile, 'refresh_token_missing');
            throw new AuthenticationException(__('Log in to Ergonode to continue this operation.'));
        }
        return $connection;
    }

    private function logAuthenticationFailure(string $profile, string $reason, ?int $httpStatus = null): void
    {
        $context = ['profile' => $profile, 'reason' => $reason];
        if ($httpStatus !== null) {
            $context['http_status'] = $httpStatus;
        }
        $this->logger->warning('Ergonode REST authentication failed.', $context);
    }

    /** @param array<string, mixed> $connection */
    private function usable(array $connection, ?string $rejectedToken): bool
    {
        return (int)$connection['expires_at'] > time() + 10 && $connection['token'] !== $rejectedToken;
    }
}
