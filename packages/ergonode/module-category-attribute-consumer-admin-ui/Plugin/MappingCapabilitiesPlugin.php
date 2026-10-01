<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Plugin;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingCapabilities;
use Magento\Backend\Model\UrlInterface;

class MappingCapabilitiesPlugin
{
    public function __construct(private readonly UrlInterface $urlBuilder)
    {
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterGetConfig(MappingCapabilities $subject, array $result, string $kind): array
    {
        return array_replace_recursive($result, [
            'allow_magento_option_creation' => true,
            'urls' => [
                'refresh' => $this->urlBuilder->getUrl('ergonode/category_' . $kind . '/refresh'),
                'delete_snapshot' => $this->urlBuilder->getUrl('ergonode/category_' . $kind . '/deleteSnapshot'),
            ],
        ]);
    }
}
