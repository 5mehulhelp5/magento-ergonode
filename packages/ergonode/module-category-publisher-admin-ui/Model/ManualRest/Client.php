<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualRest;

use Ergonode\Publisher\Api\Exception\RestRequestException as RestRequestException;
use Ergonode\Publisher\Api\Rest\ClientInterface;

class Client
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly EndpointResolver $endpointResolver
    ) {
    }

    /** @return array<string|int, mixed> */
    public function get(string $resource): array
    {
        return $this->request('GET', $resource);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string|int, mixed>
     */
    public function post(string $resource, array $payload): array
    {
        return $this->request('POST', $resource, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string|int, mixed>
     */
    public function put(string $resource, array $payload): array
    {
        return $this->request('PUT', $resource, $payload);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string|int, mixed>
     */
    private function request(string $method, string $resource, ?array $payload = null): array
    {
        $url = $this->endpointResolver->resourceUrl($resource);
        $path = (string)parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        try {
            return $this->client->request($method, $path . ($query ? '?' . $query : ''), $payload);
        } catch (RestRequestException $exception) {
            if ($exception->getStatusCode() === 429) {
                throw new RetryableRequestException($exception->getMessage(), $exception->getRetryAfterSeconds() ?? 5);
            }
            throw new RequestException($exception->getStatusCode());
        }
    }
}
