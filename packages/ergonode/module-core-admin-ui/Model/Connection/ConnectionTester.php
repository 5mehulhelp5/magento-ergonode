<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Connection;

use Ergonode\Core\Api\GraphQlRequestLimiterInterface;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;

class ConnectionTester
{
    private const string TEST_QUERY = 'query ConnectionTest { __typename }';

    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly EndpointResolver $endpointResolver,
        private readonly GraphQlRequestLimiterInterface $rateLimiter
    ) {
    }

    public function test(string $ergonodeUrl, string $apiKey, string $environment): void
    {
        $endpointUrl = $this->endpointResolver->resolveGraphQlUrl($ergonodeUrl);
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new LocalizedException(__('API Key is required to test the connection.'));
        }

        $this->curl->setHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-KEY' => $apiKey,
        ]);
        $this->curl->setTimeout(30);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);

        $payload = $this->json->serialize(['query' => self::TEST_QUERY]);
        $this->rateLimiter->throttle($environment);

        try {
            $this->curl->post($endpointUrl, $payload);
        } catch (Throwable $exception) {
            throw new LocalizedException(__('Unable to connect to Ergonode: %1', $exception->getMessage()));
        }

        $status = (int)$this->curl->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(__('Ergonode connection failed with HTTP status %1.', $status));
        }

        try {
            $response = $this->json->unserialize((string)$this->curl->getBody());
        } catch (InvalidArgumentException) {
            throw new LocalizedException(__('Ergonode returned a non-JSON response.'));
        }

        if (!is_array($response)
            || !empty($response['errors'])
            || !isset($response['data']['__typename'])
        ) {
            throw new LocalizedException(__('Ergonode rejected the connection test.'));
        }
    }
}
