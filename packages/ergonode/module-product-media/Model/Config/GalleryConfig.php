<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Config;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

class GalleryConfig implements GalleryConfigurationInterface, GalleryRulesInterface
{
    public const string XML_PATH_ENABLED = 'ergonode_products/media/synchronization_enabled';
    public const string XML_PATH_GALLERY_ATTRIBUTE = 'ergonode_products/media/gallery_attribute';
    public const string XML_PATH_IMAGES = 'ergonode_products/media/additional_images';
    public const string XML_PATH_ROLE = 'ergonode_products/media/additional_role';
    public const string XML_PATH_POSITION = 'ergonode_products/media/role_position';
    public function __construct(private readonly ScopeConfigInterface $config, private readonly Json $json)
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
    public function getAdditionalImages(): array
    {
        $raw = (string)$this->config->getValue(self::XML_PATH_IMAGES);
        if ($raw === '') {
            return [];
        }
        $rows = $this->json->unserialize($raw);
        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['attribute']] = (int)$row['position'];
        }
        return $result;
    }
    public function getAdditionalRole(): ?array
    {
        $code = trim((string)$this->config->getValue(self::XML_PATH_ROLE));
        return $code === '' ? null : [
            'attribute' => $code,
            'position' => max(2, (int)$this->config->getValue(self::XML_PATH_POSITION)),
        ];
    }
}
