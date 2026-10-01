<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Rest;

use Ergonode\Core\Api\GraphQlRequestLimiterInterface;

use Ergonode\Publisher\Api\Exception\RestRequestException as RequestException;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Throwable;

class Transport
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly GraphQlRequestLimiterInterface $limiter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string|int, mixed>
     */
    public function request(
        string $profile,
        string $origin,
        string $method,
        string $path,
        ?array $payload = null,
        ?string $token = null
    ): array {
        if (!str_starts_with($path, '/api/') || str_contains($path, '..')
            || !in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)
        ) {
            throw new LocalizedException(__('Invalid Ergonode REST request.'));
        }
        $this->limiter->throttle($profile);
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($token !== null) {
            $headers['JWTAuthorization'] = 'Bearer ' . $token;
        }
        $this->curl->setHeaders($headers);
        $this->curl->setTimeout(30);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
        try {
            if ($method === 'GET') {
                $this->curl->get($origin . $path);
            } else {
                $this->curl->post($origin . $path, $this->json->serialize($payload ?? []));
            }
        } catch (Throwable) {
            // Transport exception text can contain credentials or request bodies.
            $this->logFailure($profile, $method, $path, null);
            throw new LocalizedException(__('Unable to connect to Ergonode REST. Retry the operation.'));
        }
        $status = (int)$this->curl->getStatus();
        if ($status < 200 || $status >= 300) {
            $this->logFailure($profile, $method, $path, $status);
            throw new RequestException($status, $status === 429 ? $this->retryAfter() : null);
        }
        $body = $this->curl->getBody();
        if ($body === '') {
            return [];
        }
        try {
            $data = $this->json->unserialize($body);
        } catch (InvalidArgumentException) {
            throw new LocalizedException(__('Ergonode REST returned invalid JSON.'));
        }
        if (!is_array($data)) {
            throw new LocalizedException(__('Ergonode REST returned an invalid response.'));
        }
        return $data;
    }

    private function logFailure(string $profile, string $method, string $path, ?int $status): void
    {
        // Do not log URLs with queries, payloads, headers, response bodies or exception traces.
        $this->logger->error('Ergonode REST request failed.', [
            'profile' => $profile,
            'method' => $method,
            'path' => parse_url($path, PHP_URL_PATH),
            'http_status' => $status,
        ]);
    }

    private function retryAfter(): int
    {
        foreach ($this->curl->getHeaders() as $name => $value) {
            if (strcasecmp((string)$name, 'Retry-After') === 0 && is_scalar($value)) {
                return ctype_digit((string)$value)
                    ? max(1, (int)$value)
                    : max(1, (int)strtotime((string)$value) - time());
            }
        }
        return 5;
    }
}
