<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Magento;

use Ergonode\ProductConsumer\Api\ProductAttributeMappingDeferrerInterface;

class AsynchronousFileAttributeMapping implements ProductAttributeMappingDeferrerInterface
{
    public function supports(array $mapping): bool
    {
        $source = $this->normalize((string)$mapping['ergonode_type']);
        $target = $this->normalize((string)$mapping['magento_type']);
        return ($source === 'file' && in_array($target, ['file', 'text', 'textarea'], true))
            || ($source === 'image' && $target === 'image');
    }

    private function normalize(string $type): string
    {
        return strtolower(trim($type));
    }
}
