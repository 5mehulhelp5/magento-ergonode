<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Model;

use Ergonode\Product\Api\ProductCatalogInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Framework\UrlInterface;

class GridData
{
    public function __construct(
        private readonly ProductCatalogInterface $catalog,
        private readonly GridActionPool $actions,
        private readonly Config $mediaConfig,
        private readonly Image $image,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{total: int, page: int, items: list<array<string, int|string>>,
     *     filter_options: array{attribute_set_id: list<array{value: string, label: string}>,
     *         type_id: list<array{value: string, label: string}>},
     *     actions: list<array<string, mixed>>}
     */
    public function getPage(string $search, int $page, int $pageSize, array $criteria = []): array
    {
        $data = $this->catalog->getPage($search, $page, $pageSize, $criteria);
        foreach ($data['items'] as &$item) {
            $thumbnail = $item['thumbnail'];
            $item['thumbnail'] = $thumbnail === '' || $thumbnail === 'no_selection'
                ? $this->image->getDefaultPlaceholderUrl('thumbnail')
                : $this->mediaConfig->getMediaUrl($thumbnail);
            $item['product_url'] = $this->url->getUrl('catalog/product/edit', ['id' => $item['product_id']]);
        }
        unset($item);

        return [...$data, 'actions' => $this->actions->getActions()];
    }
}
