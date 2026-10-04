<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\OrphanImageCleanerInterface;
use Ergonode\ProductMedia\Api\GallerySynchronizerInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Model\Port\RoleWriterInterface;
use Ergonode\ProductMedia\Model\Port\GalleryWriterInterface;
use Magento\Framework\Exception\LocalizedException;

class GallerySynchronizer implements GallerySynchronizerInterface
{
    public function __construct(
        private readonly GalleryWriterInterface $gallery,
        private readonly GalleryRulesInterface $rules,
        private readonly RoleWriterInterface $roles,
        private readonly ImageRolesInterface $imageRoles,
        private readonly ?ObsoleteImageRoles $obsoleteRoles = null,
        private readonly ?OrphanImageCleanerInterface $orphanCleaner = null
    ) {
    }
    public function synchronize(int $productId, array $desired, array $managed, array $mappedRoles = []): void
    {
        usort($desired, static fn(array $a, array $b): int => $a['position'] <=> $b['position']);
        $paths = array_values(array_unique(array_column($desired, 'path')));
        $desired = [];
        foreach ($paths as $index => $path) {
            $desired[] = ['path' => $path, 'position' => $index + 1];
        }
        $first = $paths[0] ?? null;
        $roles = [0 => ['image' => $first, 'small_image' => $first, 'thumbnail' => $first]];
        $extra = $this->rules->getAdditionalRole();
        $available = $this->imageRoles->getOptions();
        if ($extra !== null) {
            if (!isset($available[$extra['attribute']]) || isset($roles[0][$extra['attribute']])
                || in_array($extra['attribute'], ['image', 'small_image', 'thumbnail'], true)
            ) {
                throw new LocalizedException(__('The additional image role is no longer available.'));
            }
            $roles[0][$extra['attribute']] = $paths[$extra['position'] - 1] ?? null;
        }
        foreach ($mappedRoles as $role) {
            if (!isset($available[$role['attribute']])) {
                continue;
            }
            if (array_key_exists($role['attribute'], $roles[0])) {
                if ($role['path'] === null) {
                    // A removed attribute mapping must not override its current positional role.
                    $roles[$role['store_id']][$role['attribute']] = $roles[0][$role['attribute']];
                    continue;
                }
                throw new LocalizedException(__('An image role cannot use both a position and an attribute mapping.'));
            }
            if ($role['path'] !== null && !in_array($role['path'], $paths, true)) {
                throw new LocalizedException(__('The mapped image must be present in the completed gallery.'));
            }
            $roles[$role['store_id']][$role['attribute']] = $role['path'];
        }
        $retired = $this->gallery->synchronize($productId, $desired, $managed);
        foreach ($roles as $store => $values) {
            $this->roles->write($productId, $store, $values);
        }
        $retired = array_values(array_unique([...$retired, ...($this->obsoleteRoles?->clearMissing($productId) ?? [])]));
        $this->orphanCleaner?->schedule($productId, $retired);
    }
}
