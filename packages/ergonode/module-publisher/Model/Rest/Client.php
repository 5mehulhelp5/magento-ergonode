<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Rest;

use Ergonode\Publisher\Api\Exception\RestRequestException as RequestException;
use Ergonode\Publisher\Api\Rest\ClientInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Client implements ClientInterface
{
    public function __construct(
        private readonly ConnectionContext $context,
        private readonly AccessTokenProvider $tokens,
        private readonly Transport $transport,
        private readonly LoggerInterface $logger
    ) {
    }

    public function request(string $method, string $path, ?array $payload = null): array
    {
        $this->context->assertAvailable();
        $profile = $this->context->profile();
        $origin = $this->context->origin();
        $connection = $this->tokens->get($profile, $origin);
        try {
            return $this->transport->request($profile, $origin, $method, $path, $payload, $connection['token']);
        } catch (RequestException $exception) {
            if ($exception->getStatusCode() !== 401) {
                throw $exception;
            }
        }
        $fresh = $this->tokens->get($profile, $origin, $connection['token']);
        if ($fresh['generation'] !== $connection['generation']) {
            throw new LocalizedException(__('The Ergonode account changed. Restart publication.'));
        }
        try {
            return $this->transport->request($profile, $origin, $method, $path, $payload, $fresh['token']);
        } catch (RequestException $exception) {
            if ($exception->getStatusCode() === 401) {
                $this->logger->warning('Ergonode REST authentication failed.', [
                    'profile' => $profile,
                    'reason' => 'renewed_token_rejected',
                    'http_status' => 401,
                ]);
                throw new AuthenticationException(__('Ergonode rejected the renewed session. Log in again.'));
            }
            throw $exception;
        }
    }
}
