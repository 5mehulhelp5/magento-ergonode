<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryTreeCreator
{
    public function __construct(
        private readonly CategoryTreeGateway $gateway,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function create(string $code, string $name): void
    {
        $code = trim($code);
        $name = trim($name);
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code)) {
            throw new LocalizedException(
                __('Tree code may contain letters, numbers, underscores and hyphens (max. 64).')
            );
        }
        if ($name === '') {
            throw new LocalizedException(__('Tree name is required.'));
        }
        $this->gateway->createTree($code, [
            $this->languageMappingProvider->requireAdminLanguageCode() => $name,
        ]);
    }
}
