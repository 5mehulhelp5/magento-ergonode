<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeOptionSynchronizerInterface;
use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeOptionSynchronizer implements AttributeOptionSynchronizerInterface
{
    public function __construct(
        private readonly AttributeStateLoader $loader,
        private readonly AttributeSynchronizerInterface $attributeSynchronizer
    ) {
    }

    public function synchronize(
        string $attributeCode,
        AttributeOptionStateInterface $desiredOption
    ): SynchronizationResultInterface {
        $remote = $this->loader->load($attributeCode, array_keys($desiredOption->getNames()));
        if ($remote === null) {
            throw new LocalizedException(__('Ergonode attribute "%1" was not found.', $attributeCode));
        }
        $options = $remote->getOptions();
        foreach ($options as $index => $option) {
            if ($option->getCode() === $desiredOption->getCode()) {
                $options[$index] = $desiredOption;
                return $this->synchronizeState($remote, $options);
            }
        }
        $options[] = $desiredOption;

        return $this->synchronizeState($remote, $options);
    }

    /** @param AttributeOptionStateInterface[] $options */
    private function synchronizeState(AttributeStateInterface $remote, array $options): SynchronizationResultInterface
    {
        return $this->attributeSynchronizer->synchronize(new AttributeState(
            $remote->getCode(),
            $remote->getType(),
            $remote->getScope(),
            $remote->getNames(),
            $remote->getParameters(),
            $remote->getMetadata(),
            $options
        ), AttributeSynchronizerInterface::MODE_UPDATE, $remote);
    }
}
