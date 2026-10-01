<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Model;

use Ergonode\ProductAdminUi\Api\GridActionProviderInterface;
use Ergonode\PublisherAdminUi\Api\WriteReadinessProviderInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;

class PublishGridAction implements GridActionProviderInterface
{
    private const string ADMIN_RESOURCE = 'Ergonode_ProductPublisher::publish';

    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url,
        private readonly WriteReadinessProviderInterface $readiness
    ) {
    }

    public function getConfiguration(): ?array
    {
        if (!$this->authorization->isAllowed(self::ADMIN_RESOURCE)) {
            return null;
        }

        return [
            'label' => (string)__('Send to Ergonode'),
            'button_label' => (string)__('Send'),
            'icon_class' => 'veui-create-ergonode-icon',
            'url' => $this->url->getUrl('ergonode_product_publisher/publication/publish'),
            'requires_mapping' => false,
            ...$this->readiness->getStatus(),
            'progress' => [
                'statusLabels' => [
                    'processing' => (string)__('Wysyłanie'),
                    'success' => (string)__('Opublikowano'),
                    'failed' => (string)__('Publikacja nieudana'),
                ],
                'title' => (string)__('Publikacja produktów do Ergonode'),
                'progressLabel' => (string)__('Postęp publikacji produktów'),
                'unit' => (string)__('produktów'),
                'processingBatch' => (string)__('Wysyłam paczkę %1 z %2 (%3 produktów).'),
                'complete' => (string)__('Publikacja zakończona. Wszystkie produkty zostały przetworzone.'),
                'partial' => (string)__(
                    'Publikacja zakończona z błędami lub ostrzeżeniami. Sprawdź szczegóły poniżej.'
                ),
            ],
        ];
    }
}
