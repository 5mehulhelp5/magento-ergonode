<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualRest;

use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeIdentityCache;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\EndpointResolver;
use Magento\Backend\Model\Auth\Session;
use PHPUnit\Framework\TestCase;

class CategoryTreeIdentityCacheTest extends TestCase
{
    public function testScopesIdentitiesToTheOriginAndInvalidatesOnlyTheRequestedTree(): void
    {
        $stored = null;
        $session = $this->createStub(Session::class);
        $session->method('getData')->willReturnCallback(static function () use (&$stored): mixed {
            return $stored;
        });
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$stored, $session): Session {
                self::assertSame('setData', $method);
                self::assertSame('ergonode_category_tree_identities', $args[0]);
                $stored = $args[1];
                return $session;
            }
        );
        $origin = 'https://one.test/api/v1/login';
        $endpoint = $this->createStub(EndpointResolver::class);
        $endpoint->method('loginUrl')->willReturnCallback(static function () use (&$origin): string {
            return $origin;
        });
        $cache = new CategoryTreeIdentityCache($session, $endpoint);
        self::assertNull($cache->get('a'));
        $cache->save('a', 'id-a');
        $cache->save('b', 'id-b');
        self::assertSame('id-a', $cache->get('a'));
        self::assertSame('id-b', $cache->get('b'));
        $cache->remove('a');
        self::assertNull($cache->get('a'));
        self::assertSame('id-b', $cache->get('b'));
        $origin = 'https://two.test/api/v1/login';
        self::assertNull($cache->get('b'));
        $cache->save('a', 'id-a-other-origin');
        self::assertSame('id-a-other-origin', $cache->get('a'));
    }
}
