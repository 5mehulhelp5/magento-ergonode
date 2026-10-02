<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Materialization;

use Ergonode\Media\Model\Data\Asset;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filter\TranslitUrl;

class ProductPathStrategy
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly File $file,
        private readonly TranslitUrl $translit
    ) {
    }

    public function resolve(Asset $asset, int $id): string
    {
        $product = $this->products->getById($id, false, 0, true);
        $sku = (string)$product->getSku();
        $sourceInfo = $this->file->getPathInfo($asset->sourcePath);
        $name = implode(
            '-',
            [
                $this->slug((string)$product->getName(), 'product'),
                $this->slug($sku, 'sku'),
                $this->slug((string)($sourceInfo['filename'] ?? ''), 'media'),
                substr(bin2hex((string)$asset->contentHash), 0, 16),
            ]
        ) . '.' . $asset->extension;
        $hash = hash('sha256', $sku);

        return sprintf(
            'catalog/product/ergonode/seo/%s/%s/%s',
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            mb_substr($name, 0, 220)
        );
    }

    private function slug(string $value, string $fallback): string
    {
        $slug = trim((string)$this->translit->filter($value), '-');
        if ($slug === '') {
            return $fallback;
        }

        return mb_substr(
            strtolower((string)preg_replace('/[^a-zA-Z0-9_-]+/', '-', $slug)),
            0,
            60
        );
    }
}
