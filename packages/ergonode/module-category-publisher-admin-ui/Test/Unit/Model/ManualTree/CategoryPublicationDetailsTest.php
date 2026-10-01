<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Ergonode\CategoryPublisher\Model\Data\CategorySynchronizationResult;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\EndpointResolver;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryPublicationDetails;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Model\Data\MutationResult;
use Magento\Backend\Model\Auth\Session;
use PHPUnit\Framework\TestCase;

class CategoryPublicationDetailsTest extends TestCase
{
    public function testNumericCodesRetainConfirmedNames(): void
    {
        $origin = 'https://one.test/api/v1/login';
        $details = $this->details($origin);
        $details->remember(7, ['0' => $this->success('0'), '123' => $this->success('123')]);
        self::assertSame('0', $details->get(7)[0]['code']);
        self::assertSame('123', $details->get(7)[123]['code']);
        self::assertSame('Chairs', $details->get(7)[123]['name'][1]['value']);
    }

    public function testKeepsSuccessAcrossBatchesAndClearsOnlyPublishedCodesWithinTheirScope(): void
    {
        $origin = 'https://one.test/api/v1/login';
        $details = $this->details($origin);
        $details->remember(7, ['a' => $this->success('a')]);
        $details->remember(7, ['b' => $this->success('b')]);
        self::assertSame(['a', 'b'], array_keys($details->get(7)));
        self::assertSame('Chairs', $details->get(7)['a']['name'][1]['value']);
        self::assertSame([], $details->get(8));
        $origin = 'https://two.test/api/v1/login';
        self::assertSame([], $details->get(7));
        $origin = 'https://one.test/api/v1/login';
        $details->clear(8, ['a']);
        self::assertCount(2, $details->get(7));
        $details->clear(7, ['a']);
        self::assertSame(['b'], array_keys($details->get(7)));
        $details->clear(7, ['b']);
        self::assertSame([], $details->get(7));
    }

    public function testCollisionAndUnconfirmedResponseNeverReuseEarlierNames(): void
    {
        $origin = 'https://one.test/api/v1/login';
        $details = $this->details($origin);
        $details->remember(7, ['a' => $this->success('a'), 'b' => $this->success('b')]);
        $details->remember(7, [
            'a' => new CategorySynchronizationResult(
                CategorySynchronizationResultInterface::STATUS_NOOP,
                CategorySynchronizationResultInterface::REFERENCE_PRESENT
            ),
            'b' => $this->success('wrong-code'),
            'c' => new CategorySynchronizationResult(
                CategorySynchronizationResultInterface::STATUS_SUCCESS,
                CategorySynchronizationResultInterface::REFERENCE_PRESENT
            ),
        ]);
        self::assertSame([], $details->get(7));
    }

    private function success(string $code): CategorySynchronizationResultInterface
    {
        return new CategorySynchronizationResult(
            CategorySynchronizationResultInterface::STATUS_SUCCESS,
            CategorySynchronizationResultInterface::REFERENCE_PRESENT,
            [new MutationResult(
                MutationResultInterface::STATUS_SUCCESS,
                'created',
                $this->createStub(MutationOperationInterface::class),
                ['category' => ['code' => $code, 'name' => [
                    ['language' => 'pl_PL', 'value' => 'Krzesła'],
                    ['language' => 'en_GB', 'value' => 'Chairs'],
                ]]]
            )]
        );
    }

    private function details(string &$origin): CategoryPublicationDetails
    {
        $stored = null;
        $session = $this->createStub(Session::class);
        $session->method('getData')->willReturnCallback(static function () use (&$stored): mixed {
            return $stored;
        });
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$stored, $session): Session {
                self::assertSame('setData', $method);
                self::assertSame('ergonode_category_publication_details', $args[0]);
                $stored = $args[1];
                return $session;
            }
        );
        $endpoint = $this->createStub(EndpointResolver::class);
        $endpoint->method('loginUrl')->willReturnCallback(static function () use (&$origin): string {
            return $origin;
        });

        return new CategoryPublicationDetails($session, $endpoint);
    }
}
