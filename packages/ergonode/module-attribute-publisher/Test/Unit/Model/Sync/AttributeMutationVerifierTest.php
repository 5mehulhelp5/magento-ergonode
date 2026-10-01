<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\AttributePublisher\Model\Sync\AttributeMutationVerifier;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\AttributePublisher\Model\Sync\AttributeSyncPlanner;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AttributeMutationVerifierTest extends TestCase
{
    public function testReconcileVerificationUsesFullLanguageScope(): void
    {
        $desired = new AttributeState('color', 'select', 'LOCAL', ['en_US' => 'Color']);
        $remote = new AttributeState('color', 'select', 'LOCAL', ['en_US' => 'Color', 'pl_PL' => 'Kolor']);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::once())->method('load')->with('color', [])->willReturn($remote);
        $factory = new AttributeMutationFactory();
        $verifier = new AttributeMutationVerifier($loader, new AttributeSyncPlanner($factory), $desired, 'reconcile');
        self::assertSame('not_applied', $verifier->verify($factory->setName($desired))->getStatus());
    }

    public function testOneReadPerRoundForManyOptionsAndFreshReadAfterNextWrite(): void
    {
        $options = array_map(static fn (int $index) => new AttributeOptionState('o' . $index, []), range(1, 20));
        $desired = new AttributeState('color', 'select', 'LOCAL', [], [], [], $options);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')->willReturnOnConsecutiveCalls(
            $desired,
            new AttributeState('color', 'select', 'LOCAL')
        );
        $factory = new AttributeMutationFactory();
        $verifier = new AttributeMutationVerifier($loader, new AttributeSyncPlanner($factory), $desired, 'update');
        $verifier->beginVerificationRound();
        foreach ($options as $option) {
            self::assertSame(
                'applied',
                $verifier->verify($factory->addOption('color', 'select', $option))->getStatus()
            );
        }
        $verifier->beginVerificationRound();
        foreach ($options as $option) {
            self::assertSame(
                'not_applied',
                $verifier->verify($factory->addOption('color', 'select', $option))->getStatus()
            );
        }
    }

    public function testFailedReadIsSharedWithinRoundAndRetriedOnlyInNextRound(): void
    {
        $desired = new AttributeState('color', 'text', 'LOCAL');
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')->willThrowException(new RuntimeException('read failed'));
        $factory = new AttributeMutationFactory();
        $verifier = new AttributeMutationVerifier($loader, new AttributeSyncPlanner($factory), $desired, 'update');
        for ($round = 0; $round < 2; ++$round) {
            $verifier->beginVerificationRound();
            for ($operation = 0; $operation < 20; ++$operation) {
                try {
                    $verifier->verify($factory->setName($desired));
                    self::fail('Expected verification failure.');
                } catch (MutationVerificationException $exception) {
                    self::assertStringContainsString('read failed', $exception->getMessage());
                }
            }
        }
    }
}
