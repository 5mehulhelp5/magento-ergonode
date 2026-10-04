<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Config;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\ImageRulesNormalizerInterface;
use Ergonode\ProductMedia\Exception\InvalidMediaConfigurationException;
use InvalidArgumentException;
use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

class GalleryConfig implements GalleryConfigurationInterface, GalleryRulesInterface
{
    public const string XML_PATH_ENABLED = 'ergonode_products/media/synchronization_enabled';
    public const string XML_PATH_GALLERY_ATTRIBUTE = 'ergonode_products/media/gallery_attribute';
    public const string XML_PATH_IMAGES = 'ergonode_products/media/additional_images';
    public const string XML_PATH_ROLE = 'ergonode_products/media/additional_role';
    public const string XML_PATH_UNMANAGED_IMAGES = 'ergonode_products/media/unmanaged_images';
    public const string XML_PATH_POSITION = 'ergonode_products/media/role_position';
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly Json $json,
        private readonly ImageRulesNormalizerInterface $normalizer
    )
    {
    }
    public function isSynchronizationEnabled(): bool
    {
        return $this->config->isSetFlag(self::XML_PATH_ENABLED);
    }
    public function getGalleryAttributeCode(): string
    {
        return trim((string)$this->config->getValue(self::XML_PATH_GALLERY_ATTRIBUTE));
    }
    public function getUnmanagedImagesMode(): UnmanagedImagesMode
    {
        return UnmanagedImagesMode::fromConfig($this->config->getValue(self::XML_PATH_UNMANAGED_IMAGES));
    }
    public function getAdditionalImages(): array
    {
        $raw = $this->config->getValue(self::XML_PATH_IMAGES);
        if ($raw === null || $raw === '') {
            return [];
        }
        try {
            if (!is_string($raw)) {
                throw new InvalidMediaConfigurationException(__('Additional image configuration must contain a JSON list of rules.'));
            }
            $rows = $this->json->unserialize($raw);
            if (!is_array($rows)) {
                throw new InvalidMediaConfigurationException(__('Additional image configuration must contain a JSON list of rules.'));
            }
            $rows = $this->normalizer->normalize($rows);
        } catch (InvalidArgumentException|InvalidMediaConfigurationException $exception) {
            throw new InvalidMediaConfigurationException(
                __('Invalid media setting "%1": %2.', self::XML_PATH_IMAGES, $exception->getMessage()), $exception
            );
        }
        $result = [];
        foreach ($rows as $row) {
            $result[$row['attribute']] = $row['position'];
        }
        return $result;
    }
    public function getAdditionalRole(): ?array
    {
        $code = trim((string)$this->config->getValue(self::XML_PATH_ROLE));
        return $code === '' ? null : [
            'attribute' => $code,
            'position' => $this->normalizer->normalizePosition($this->config->getValue(self::XML_PATH_POSITION)),
        ];
    }
}
