<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Sync;

use Ergonode\AttributeConsumer\Api\OptionSynchronizationParticipantInterface;
use Ergonode\ProductAttributeConsumer\Api\OptionSynchronizationProcessInterface;

class ProductOptionSynchronizationParticipant implements OptionSynchronizationParticipantInterface
{
    public function __construct(
        private readonly OptionSynchronizationProcessInterface $optionSynchronizationProcess
    ) {
    }

    public function executeForAttributeCodes(array $attributeCodes): array
    {
        return $this->optionSynchronizationProcess->executeForAttributeCodes($attributeCodes);
    }
}
