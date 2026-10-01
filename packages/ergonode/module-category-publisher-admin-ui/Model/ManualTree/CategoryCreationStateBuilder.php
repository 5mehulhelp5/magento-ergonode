<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Ergonode\CategoryPublisher\Api\CategoryDesiredStateFactoryInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryCreationStateBuilder
{
    private ?string $language = null;

    public function __construct(
        private readonly CategoryDesiredStateFactoryInterface $stateFactory,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function validationError(string $code, string $label): ?string
    {
        if (!preg_match('/^[a-z0-9_]{1,128}$/', $code)) {
            return (string)__('Invalid Ergonode category code "%1".', $code);
        }
        if ($label === '') {
            return (string)__('Ergonode category label is required.');
        }

        return null;
    }

    public function build(string $code, string $label): CategoryStateInterface
    {
        $validationError = $this->validationError($code, $label);
        if ($validationError !== null) {
            throw new LocalizedException(__($validationError));
        }
        $this->language ??= $this->languageMappingProvider->requireAdminLanguageCode();

        return $this->stateFactory->createCategory($code, [$this->language => $label]);
    }

    public function collisionMessage(string $code, string $label): string
    {
        return (string)__(
            'Category "%1" was skipped because Ergonode category code "%2" is already used.',
            $label,
            $code
        );
    }
}
