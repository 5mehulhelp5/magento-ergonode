<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl;

use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Api\GraphQlRequestLimiterInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Exception;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Client implements
    GraphQlMutationClientInterface,
    GraphQlQueryClientInterface,
    GraphQlWriteScopeQueryClientInterface
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly ConfigProvider $configProvider,
        private readonly ReadOnlyGuard $readOnlyGuard,
        private readonly MutationGuard $mutationGuard,
        private readonly GraphQlRequestLimiterInterface $rateLimiter,
        private readonly LoggerInterface $logger
    ) {
    }

    /** Probe without starting domain work or logging expected connection failures. */
    public function isConnectionAvailable(): bool
    {
        if (!$this->configProvider->isEnabled()) {
            return false;
        }
        try {
            $response = $this->request(
                'query ConnectionTest { __typename }',
                [],
                $this->configProvider->getApiKey(),
                false
            );
        } catch (GraphQlRequestException) {
            return false;
        }

        return empty($response['errors'])
            && is_string($response['data']['__typename'] ?? null)
            && $response['data']['__typename'] !== '';
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function query(string $document, array $variables = [], bool $requireEnabled = true): array
    {
        $this->readOnlyGuard->assertQuery($document);

        if ($requireEnabled && !$this->configProvider->isEnabled()) {
            throw new LocalizedException(__('Ergonode integration is disabled.'));
        }

        $apiKey = $this->configProvider->getApiKey();

        return $this->unwrapResponse($this->request($document, $variables, $apiKey));
    }

    /**
     * @param array<string, mixed> $variables
     * @return array{data?: array<string, mixed>|null, errors?: array<int, array<string, mixed>>}
     * @throws LocalizedException
     */
    public function mutateWithResponse(string $document, array $variables = []): array
    {
        $apiKey = $this->mutationGuard->authorize($document);

        $response = $this->request($document, $variables, $apiKey);

        return $this->normalizeResponseEnvelope($response);
    }

    public function queryWriteScope(string $document, array $variables = []): array
    {
        $this->readOnlyGuard->assertQuery($document);

        return $this->unwrapResponse($this->request(
            $document,
            $variables,
            $this->mutationGuard->authorizeUpdateScope()
        ));
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function request(
        string $document,
        array $variables,
        string $apiKey,
        bool $logFailures = true
    ): array {
        $url = $this->resolveEndpointUrl($this->configProvider->getGraphQlUrl());

        if ($url === '') {
            throw new ConnectionConfigurationException(
                (string)__('Ergonode GraphQL URL is missing.'),
                GraphQlRequestException::FAILURE_REQUEST_CONSTRUCTION
            );
        }
        if (trim($apiKey) === '') {
            throw new ConnectionConfigurationException(
                (string)__('Ergonode GraphQL API key is missing.'),
                GraphQlRequestException::FAILURE_AUTHORIZATION
            );
        }

        try {
            $payload = $this->json->serialize([
                'query' => $document,
                'variables' => $variables,
            ]);
        } catch (InvalidArgumentException $exception) {
            throw new GraphQlRequestException(
                (string)__('Unable to serialize Ergonode GraphQL request: %1', $exception->getMessage()),
                GraphQlRequestException::FAILURE_REQUEST_CONSTRUCTION
            );
        }

        $this->rateLimiter->throttle();

        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->addHeader('X-API-KEY', $apiKey);
        $this->curl->setTimeout(30);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        try {
            $this->curl->post($url, $payload);
        } catch (Exception $exception) {
            if ($logFailures) {
                $this->logger->error('Unable to connect to Ergonode GraphQL endpoint.', [
                    'exception' => $exception,
                    'url' => $url,
                ]);
            }
            throw new GraphQlRequestException(
                (string)__('Unable to connect to Ergonode GraphQL endpoint: %1', $exception->getMessage()),
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT
            );
        }

        $status = (int)$this->curl->getStatus();
        $body = (string)$this->curl->getBody();

        if ($status < 200 || $status >= 300) {
            if (in_array($status, [401, 403, 404], true)) {
                throw new ConnectionConfigurationException(
                    (string)__('Ergonode GraphQL request failed with HTTP status %1.', $status),
                    $this->resolveHttpFailureType($status),
                    $status
                );
            }
            if ($logFailures) {
                $this->logger->error('Ergonode GraphQL request failed.', [
                    'status' => $status,
                    'url' => $url,
                    'body' => mb_substr($body, 0, 1000),
                ]);
            }
            throw new GraphQlRequestException(
                (string)__('Ergonode GraphQL request failed with HTTP status %1.', $status),
                $this->resolveHttpFailureType($status),
                $status,
                $this->resolveRetryAfterSeconds((array)$this->curl->getHeaders())
            );
        }

        try {
            $decoded = $body !== '' ? $this->json->unserialize($body) : [];
        } catch (InvalidArgumentException) {
            throw new ConnectionConfigurationException(
                (string)__(
                    'Ergonode GraphQL returned a non-JSON response. Check GraphQL URL; '
                    . 'for Ergonode Cloud use the API endpoint ending with /api/graphql/.'
                ),
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT
            );
        }

        if (!is_array($decoded)) {
            throw new GraphQlRequestException(
                (string)__('Ergonode GraphQL returned an invalid response.'),
                GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $response
     * @return array{data?: array<string, mixed>|null, errors?: array<int, array<string, mixed>>}
     * @throws LocalizedException
     */
    private function normalizeResponseEnvelope(array $response): array
    {
        $envelope = [];

        if (array_key_exists('data', $response)) {
            if ($response['data'] !== null && !is_array($response['data'])) {
                throw new GraphQlRequestException(
                    (string)__('Ergonode GraphQL returned invalid response data.'),
                    GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT
                );
            }
            $envelope['data'] = $response['data'];
        }

        if (array_key_exists('errors', $response)) {
            if (!is_array($response['errors'])) {
                throw new GraphQlRequestException(
                    (string)__('Ergonode GraphQL returned invalid response errors.'),
                    GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT
                );
            }
            foreach ($response['errors'] as $error) {
                if (!is_array($error)) {
                    throw new GraphQlRequestException(
                        (string)__('Ergonode GraphQL returned an invalid response error.'),
                        GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT
                    );
                }
                $envelope['errors'][] = $error;
            }
        }

        return $envelope;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function unwrapResponse(array $response): array
    {
        if (!empty($response['errors'])) {
            throw new LocalizedException(__($this->formatErrors((array)$response['errors'])));
        }

        return isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function resolveRetryAfterSeconds(array $headers): ?int
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string)$name, 'Retry-After') !== 0 || !is_scalar($value)) {
                continue;
            }

            $retryAfter = trim((string)$value);
            if (ctype_digit($retryAfter)) {
                return max(0, (int)$retryAfter);
            }

            $timestamp = strtotime($retryAfter);

            return $timestamp === false ? null : max(0, $timestamp - time());
        }

        return null;
    }

    private function resolveHttpFailureType(int $status): string
    {
        if ($status === 401 || $status === 403) {
            return GraphQlRequestException::FAILURE_AUTHORIZATION;
        }
        if ($status === 429) {
            return GraphQlRequestException::FAILURE_RATE_LIMIT;
        }
        if ($status >= 500 && $status <= 599) {
            return GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT;
        }

        return GraphQlRequestException::FAILURE_PERMANENT_TRANSPORT;
    }

    private function resolveEndpointUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
        ) {
            throw new ConnectionConfigurationException(
                (string)__('Configure a valid HTTP or HTTPS Ergonode GraphQL URL.'),
                GraphQlRequestException::FAILURE_REQUEST_CONSTRUCTION
            );
        }
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === false || $path === null || $path === '' || $path === '/') {
            return rtrim($url, '/') . '/api/graphql/';
        }

        if (preg_match('#/api/graphql/?$#', $path)) {
            return rtrim($url, '/') . '/';
        }

        return $url;
    }

    /**
     * @param array<int, mixed> $errors
     */
    private function formatErrors(array $errors): string
    {
        $messages = [];

        foreach ($errors as $error) {
            if (is_array($error) && isset($error['message'])) {
                $messages[] = (string)$error['message'];
            }
        }

        return $messages ? implode(' ', $messages) : 'Ergonode GraphQL request failed.';
    }
}
