<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Model;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\PublisherAdminUi\Api\WriteReadinessProviderInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;

class WriteReadinessProvider implements WriteReadinessProviderInterface
{
    private const string CONFIGURATION_RESOURCE = 'Ergonode_Core::config';

    public function __construct(
        private readonly ConfigProvider $configuration,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url
    ) {
    }

    public function getStatus(): array
    {
        $problems = [];
        if (!$this->configuration->isEnabled()) {
            $problems[] = (string)__(
                'Enable the active Ergonode connection and choose an available operating mode.'
            );
        } elseif (!$this->configuration->allowsWrites()) {
            $problems[] = (string)__('Choose Read and write mode to allow creating categories.');
        } elseif (trim($this->configuration->getApiKey()) === '') {
            $problems[] = (string)__('The read-and-write API key for the active environment is missing.');
        }
        $canConfigure = $this->authorization->isAllowed(self::CONFIGURATION_RESOURCE);
        $message = $problems === [] ? '' : (string)__(
            'Creating categories in Ergonode is unavailable. %1 %2',
            implode(' ', $problems),
            $canConfigure
                ? __('Check the active environment, operating mode and API key in Connection configuration.')
                : __('Ask an administrator to check the active environment, operating mode and API key.')
        );

        return [
            'ready' => $problems === [],
            'message' => $message,
            'configuration_url' => $canConfigure
                ? $this->url->getUrl('adminhtml/system_config/edit', ['section' => 'ergonode_connection'])
                : '',
        ];
    }
}
