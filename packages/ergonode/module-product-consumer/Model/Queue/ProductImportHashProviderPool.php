<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Queue;

use Ergonode\ProductConsumer\Api\ProductImportHashProviderInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

class ProductImportHashProviderPool
{
    /** @param ProductImportHashProviderInterface[] $providers */
    public function __construct(private readonly array $providers = [])
    {
    }

    /** @return array<string, string> */
    public function getHashes(RemoteProduct $source): array
    {
        $hashes = [];
        foreach ($this->providers as $name => $provider) {
            $hashes[(string)$name] = $provider->getHash($source);
        }
        ksort($hashes);

        return $hashes;
    }
}
