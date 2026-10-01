<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualRest;

use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\Client;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\EndpointResolver;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\RetryableRequestException;
use Ergonode\Publisher\Api\Exception\RestRequestException as RequestException;
use Ergonode\Publisher\Api\Rest\ClientInterface;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testDelegatesResourcePathAndQueryToSharedClient(): void
    {
        $shared = $this->createMock(ClientInterface::class);
        $shared->expects(self::once())->method('request')->with(
            'GET',
            '/api/v1/pl_PL/trees?limit=50',
            null
        )->willReturn(['id' => 'tree']);
        $endpoint = $this->createStub(EndpointResolver::class);
        $endpoint->method('resourceUrl')->willReturn('https://example.test/api/v1/pl_PL/trees?limit=50');
        self::assertSame(['id' => 'tree'], (new Client($shared, $endpoint))->get('trees?limit=50'));
    }

    public function testPreservesDomainRetryContract(): void
    {
        $shared = $this->createStub(ClientInterface::class);
        $shared->method('request')->willThrowException(new RequestException(429, 12));
        $endpoint = $this->createStub(EndpointResolver::class);
        $endpoint->method('resourceUrl')->willReturn('https://example.test/api/v1/pl_PL/trees');
        try {
            (new Client($shared, $endpoint))->get('trees');
            self::fail('Rate limit must propagate.');
        } catch (RetryableRequestException $exception) {
            self::assertSame(12, $exception->getRetryAfterSeconds());
        }
    }
}
