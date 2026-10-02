<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\GraphQl;

use Ergonode\Media\Api\ImageAttributeOptionsInterface;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Media\Api\GalleryAttributeOptionsInterface;
use Magento\Framework\Exception\LocalizedException;

class GalleryAttributeOptions implements GalleryAttributeOptionsInterface, ImageAttributeOptionsInterface
{
    public const string QUERY = <<<'GRAPHQL'
query ErgonodeGalleryAttributes($after: String) {
  attributeStream(first: 100, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { node { __typename code name { language value } } }
  }
}
GRAPHQL;

    public function __construct(
        private readonly GraphQlQueryClientInterface $client,
        private readonly string $attributeType = 'GalleryAttribute'
    ) {
    }

    public function getOptions(): array
    {
        $options = [];
        $cursor = null;
        $seen = [];
        do {
            $data = $this->client->query(self::QUERY, ['after' => $cursor], false);
            $page = $data['attributeStream'] ?? null;
            if (!is_array($page) || !is_array($page['edges'] ?? null)
                || !is_array($page['pageInfo'] ?? null)
            ) {
                throw new LocalizedException(__('Ergonode returned an invalid attribute list.'));
            }
            foreach ($page['edges'] as $edge) {
                $node = $edge['node'] ?? [];
                if (($node['__typename'] ?? '') !== $this->attributeType) {
                    continue;
                }
                $code = trim((string)($node['code'] ?? ''));
                if ($code === '') {
                    throw new LocalizedException(__('Ergonode returned a gallery without an attribute code.'));
                }
                $labels = [];
                foreach ((array)($node['name'] ?? []) as $translation) {
                    $label = trim((string)($translation['value'] ?? ''));
                    if ($label !== '') {
                        $labels[] = $label;
                    }
                }
                $options[$code] = $labels === [] ? $code : implode(' / ', array_unique($labels)) . ' (' . $code . ')';
            }
            $more = !empty($page['pageInfo']['hasNextPage']);
            $cursor = trim((string)($page['pageInfo']['endCursor'] ?? ''));
            if ($more && ($cursor === '' || isset($seen[$cursor]))) {
                throw new LocalizedException(__('Ergonode returned an invalid attribute cursor.'));
            }
            $seen[$cursor] = true;
        } while ($more);
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }
}
