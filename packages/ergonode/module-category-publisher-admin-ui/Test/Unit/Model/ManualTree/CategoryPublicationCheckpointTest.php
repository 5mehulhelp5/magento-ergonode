<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\EndpointResolver;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationCheckpoint;
use Magento\Backend\Model\Auth\Session;
use PHPUnit\Framework\TestCase;

class CategoryPublicationCheckpointTest extends TestCase
{
    public function testCheckpointOnlyReplaysSameBatchTreeAndEndpointAndClearsOnCompletion(): void
    {
        $stored = null;
        $session = $this->createMock(Session::class);
        $session->method('getData')->willReturnCallback(static function () use (&$stored): mixed {
            return $stored;
        });
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$stored, $session): Session {
                self::assertSame('ergonode_category_publication', $args[0]);
                self::assertContains($method, ['setData', 'unsetData']);
                $stored = $method === 'setData' ? $args[1] : null;
                return $session;
            }
        );
        $origin = 'https://example.test/api/v1/login';
        $endpoint = $this->createMock(EndpointResolver::class);
        $endpoint->method('loginUrl')->willReturnCallback(static function () use (&$origin): string {
            return $origin;
        });
        $checkpoint = new CategoryPublicationCheckpoint($session, $endpoint);
        $items = [['code' => 'chairs', 'label' => 'Chairs']];
        self::assertSame([], $checkpoint->get(7, $items));
        $checkpoint->save(7, $items, ['chairs']);
        self::assertSame(['chairs' => true], $checkpoint->get(7, $items));
        self::assertSame([], $checkpoint->get(8, $items));
        self::assertSame([], $checkpoint->get(7, [['code' => 'chairs', 'label' => 'Changed']]));
        $origin = 'https://another.test/api/v1/login';
        self::assertSame([], $checkpoint->get(7, $items));
        $origin = 'https://example.test/api/v1/login';
        $checkpoint->clear(7, $items);
        self::assertSame([], $checkpoint->get(7, $items));
    }

    public function testBatchAndLayoutReceiptsDoNotReplaceEachOther(): void
    {
        $stored = [];
        $session = $this->createStub(Session::class);
        $session->method('getData')->willReturnCallback(
            static function (string $key) use (&$stored): mixed {
                return $stored[$key] ?? null;
            }
        );
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$stored, $session): Session {
                if ($method === 'setData') {
                    $stored[$args[0]] = $args[1];
                } else {
                    unset($stored[$args[0]]);
                }
                return $session;
            }
        );
        $endpoint = $this->createStub(EndpointResolver::class);
        $endpoint->method('loginUrl')->willReturn('https://example.test/api/v1/login');
        $checkpoint = new CategoryPublicationCheckpoint($session, $endpoint);
        $items = [['code' => 'a'], ['code' => 'b']];
        $checkpoint->save(7, $items, ['a'], 'layout');
        $checkpoint->save(7, [$items[1]], ['b']);
        self::assertSame(['a' => true], $checkpoint->get(7, $items, 'layout'));
        self::assertSame(['b' => true], $checkpoint->get(7, [$items[1]]));
        $checkpoint->clear(7, [$items[1]]);
        self::assertSame(['a' => true], $checkpoint->get(7, $items, 'layout'));
    }
}
