<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\E2e\Support;

use InvalidArgumentException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use RuntimeException;

class UserRestClient
{
    private string $token = '';

    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly string $origin,
        private readonly string $languageCode
    ) {
    }

    public function login(string $username, string $password): void
    {
        $response = $this->request('POST', '/api/v1/login', [
            'username' => trim($username),
            'password' => $password,
        ], false);
        $this->token = trim((string)($response['token'] ?? ''));
        if ($this->token === '') {
            throw new RuntimeException('Ergonode REST login did not return a JWT token.');
        }
    }

    /** @return array<string|int, mixed> */
    public function get(string $resource): array
    {
        return $this->request('GET', $this->resourcePath($resource));
    }

    /** @param array<string, mixed> $payload @return array<string|int, mixed> */
    public function post(string $resource, array $payload): array
    {
        return $this->request('POST', $this->resourcePath($resource), $payload);
    }

    /** @param array<string, mixed> $payload @return array<string|int, mixed> */
    public function put(string $resource, array $payload): array
    {
        return $this->request('PUT', $this->resourcePath($resource), $payload);
    }

    public function delete(string $resource): void
    {
        $this->request('DELETE', $this->resourcePath($resource), []);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $resource, string $code): ?array
    {
        return $this->findByField($resource, 'code', $code);
    }

    /** @return array<string, mixed>|null */
    public function findByField(string $resource, string $field, string $value): ?array
    {
        $field = trim($field);
        $value = trim($value);
        if ($field === '' || $value === '') {
            return null;
        }
        $response = $this->get($resource . '?' . http_build_query([
            'limit' => 50,
            'offset' => 0,
            'filter' => $field . '=' . $value,
            'view' => 'list',
        ]));

        return $this->findEntity($response, $field, $value);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string|int, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, bool $authenticated = true): array
    {
        if ($authenticated && $this->token === '') {
            throw new RuntimeException('Ergonode REST user is not authenticated.');
        }
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($authenticated) {
            $headers['JWTAuthorization'] = 'Bearer ' . $this->token;
        }
        $this->curl->setHeaders($headers);
        $this->curl->setTimeout(60);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
        $url = rtrim($this->origin, '/') . '/' . ltrim($path, '/');
        if ($method === 'GET') {
            $this->curl->get($url);
        } else {
            $this->curl->post($url, $this->json->serialize($payload ?? []));
        }

        $status = (int)$this->curl->getStatus();
        $body = (string)$this->curl->getBody();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf(
                'Ergonode REST %s %s failed with HTTP %d: %s',
                $method,
                $path,
                $status,
                mb_substr($body, 0, 1000)
            ));
        }
        if ($body === '') {
            return [];
        }
        try {
            $decoded = $this->json->unserialize($body);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Ergonode REST returned invalid JSON.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function resourcePath(string $resource): string
    {
        return '/api/v1/' . rawurlencode($this->languageCode) . '/' . ltrim($resource, '/');
    }

    /** @param array<string|int, mixed> $response @return array<string, mixed>|null */
    private function findEntity(array $response, string $field, string $expectedValue): ?array
    {
        if ((string)($response[$field] ?? '') === $expectedValue
            && trim((string)($response['id'] ?? '')) !== ''
        ) {
            return $response;
        }
        foreach ($response as $child) {
            if (!is_array($child)) {
                continue;
            }
            $entity = $this->findEntity($child, $field, $expectedValue);
            if ($entity !== null) {
                return $entity;
            }
        }

        return null;
    }
}
