<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Exception;

use Magento\Framework\Exception\LocalizedException;

class MissingOptionMappingException extends LocalizedException
{
    /** @param string[] $optionIds */
    public function __construct(private readonly array $optionIds, string $context)
    {
        parent::__construct(__(
            'Magento option ID(s) "%1" have no Ergonode mapping for %2.',
            implode(', ', $optionIds),
            $context
        ));
    }

    /** @return string[] */
    public function getOptionIds(): array
    {
        return $this->optionIds;
    }
}
