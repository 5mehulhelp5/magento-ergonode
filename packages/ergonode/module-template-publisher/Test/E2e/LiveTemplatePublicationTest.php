<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Test\E2e;

use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\TemplatePublisher\Api\TemplateCreatorInterface;
use Ergonode\TemplatePublisher\Test\E2e\Support\RemoteTemplateFixture;
use Magento\Framework\App\Bootstrap;
use PHPUnit\Framework\TestCase;

class LiveTemplatePublicationTest extends TestCase
{
    public function testCreatesReadsRetriesAndRemovesRemoteTemplate(): void
    {
        $manager = Bootstrap::create(dirname(__DIR__, 5), $_SERVER)->getObjectManager();
        $config = $manager->get(ConfigProvider::class);
        self::assertSame('test', $config->getEnvironment(), 'Live template fixtures require the test tenant.');
        self::assertTrue($config->allowsWrites(), 'Live template fixtures require the write connection mode.');

        $fixture = new RemoteTemplateFixture(
            $manager->get(GraphQlWriteScopeQueryClientInterface::class),
            $manager->get(GraphQlMutationClientInterface::class)
        );
        $creator = $manager->get(TemplateCreatorInterface::class);
        $code = $fixture->uniqueCode('identity');

        self::assertNull($fixture->find($code), 'The unique remote fixture code must be unused before creation.');
        try {
            $creator->create($code, ['pl_PL' => 'Codex template publisher identity']);
            self::assertSame($code, $fixture->requireVisible($code)['code'] ?? null);

            $creator->create($code, ['pl_PL' => 'A retry must not create a duplicate']);
            self::assertSame($code, $fixture->requireVisible($code)['code'] ?? null);
        } finally {
            $fixture->removeAndVerify($code);
        }
    }
}
