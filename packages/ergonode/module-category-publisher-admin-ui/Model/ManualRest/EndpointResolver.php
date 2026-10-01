<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualRest;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class EndpointResolver
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function loginUrl(): string
    {
        return $this->origin() . '/api/v1/login';
    }

    public function resourceUrl(string $resource): string
    {
        $language = rawurlencode($this->languageMappingProvider->requireAdminLanguageCode());

        return $this->origin() . '/api/v1/' . $language . '/' . ltrim($resource, '/');
    }

    private function origin(): string
    {
        $url = $this->configProvider->getGraphQlUrl();
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new LocalizedException(__('Configure a valid Ergonode GraphQL URL before manual operations.'));
        }

        $origin = (string)$parts['scheme'] . '://' . (string)$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }

        return $origin;
    }
}
