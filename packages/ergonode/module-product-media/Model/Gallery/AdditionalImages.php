<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryLayoutInterface;
use Magento\Framework\Exception\LocalizedException;

class AdditionalImages implements GalleryLayoutInterface
{
    /**
     * @param list<string> $gallery
     * @param array<string,int> $positions Source path to requested position.
     * @return list<string>
     */
    public function arrange(array $gallery, array $positions): array
    {
        $gallery = array_values(array_unique($gallery));
        asort($positions, SORT_NUMERIC);
        $primary = $gallery[0] ?? null;
        $used = [];
        foreach ($positions as $path => $position) {
            if ($position < 2 || isset($used[$position])) {
                throw new LocalizedException(__('Additional images require distinct positions starting from 2.'));
            }
            $used[$position] = true;
            if ($path === $primary) {
                continue;
            }
            $gallery = array_values(array_filter($gallery, static fn(string $item): bool => $item !== $path));
            array_splice($gallery, min($position - 1, count($gallery)), 0, [$path]);
        }
        return $gallery;
    }
}
