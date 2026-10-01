<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CategoryNameMode implements OptionSourceInterface
{
    /** @param array<string, string> $additionalOptions */
    public function __construct(private readonly array $additionalOptions = [])
    {
    }

    /** @return list<array{value: string, label: \Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (['manual' => 'Keep', 'source' => 'Update'] + $this->additionalOptions as $value => $label) {
            $options[] = ['value' => $value, 'label' => __($label)];
        }

        return $options;
    }
}
