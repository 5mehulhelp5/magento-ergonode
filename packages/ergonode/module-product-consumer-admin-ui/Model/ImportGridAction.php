<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumerAdminUi\Model;

use Ergonode\ProductAdminUi\Api\GridActionProviderInterface;
use Ergonode\ProductConsumer\Api\ProductImportReadinessInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;

class ImportGridAction implements GridActionProviderInterface
{
    private const string ADMIN_RESOURCE = 'Ergonode_ProductConsumer::import';

    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url,
        private readonly ProductImportReadinessInterface $readiness
    ) {
    }

    public function getConfiguration(): ?array
    {
        if (!$this->authorization->isAllowed(self::ADMIN_RESOURCE)) {
            return null;
        }

        return [
            'label' => (string)__('Download from Ergonode'),
            'button_label' => (string)__('Download'),
            'primary' => true,
            'icon_class' => 'veui-refresh-ergonode-icon',
            'url' => $this->url->getUrl('ergonode_product_consumer/product/import'),
            'requires_mapping' => true,
            ...$this->readiness->getStatus(),
            'progress' => [
                'statusLabels' => [
                    'processing' => (string)__('Pobieranie'),
                    'success' => (string)__('Pobrano'),
                    'failed' => (string)__('Pobieranie nieudane'),
                ],
                'title' => (string)__('Pobieranie danych produktów do Magento'),
                'progressLabel' => (string)__('Postęp pobierania danych produktów'),
                'unit' => (string)__('produktów'),
                'processingBatch' => (string)__('Pobieram paczkę %1 z %2 (%3 produktów).'),
                'complete' => (string)__('Pobieranie zakończone. Wszystkie produkty zostały przetworzone.'),
                'partial' => (string)__(
                    'Pobieranie zakończone z błędami lub ostrzeżeniami. Sprawdź szczegóły poniżej.'
                ),
            ],
        ];
    }
}
