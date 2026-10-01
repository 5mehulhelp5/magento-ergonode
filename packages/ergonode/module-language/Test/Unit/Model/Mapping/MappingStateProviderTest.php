<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Mapping;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Language\Api\ErgonodeLanguageCodesProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingRowsProviderInterface;
use Ergonode\Language\Api\StoreViewProviderInterface;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Mapping\MappingStateProvider;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class MappingStateProviderTest extends TestCase
{
    public function testDefaultValuesStayActiveRegardlessOfStoredVisibility(): void
    {
        $rows = $this->createStub(LanguageStoreMappingRowsProviderInterface::class);
        $rows->method('getRows')->willReturn([]);
        $codes = $this->createStub(ErgonodeLanguageCodesProviderInterface::class);
        $codes->method('getErgonodeLanguageCodes')->willReturn(['en_GB']);
        $stores = $this->createStub(StoreViewProviderInterface::class);
        $stores->method('getStoreViewMap')->willReturn([
            0 => ['id' => 0, 'code' => 'admin', 'name' => 'Admin', 'locale' => 'en_GB',
                'website' => 'Global', 'group' => 'All'],
        ]);
        $visibility = $this->createStub(MappingVisibilityProviderInterface::class);
        $visibility->method('getActiveMap')->willReturnCallback(
            static fn (string $type, string $source): array => $source === 'magento'
                ? ['0' => false] : ['en_GB' => false]
        );
        $lock = $this->createStub(MappingLock::class);
        $lock->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $state = (new MappingStateProvider($rows, $codes, $stores, $visibility, $lock, new Json()))->getState();

        self::assertTrue($state->storeVisibility['0']);
        self::assertFalse($state->languageVisibility['en_GB']);
    }
}
