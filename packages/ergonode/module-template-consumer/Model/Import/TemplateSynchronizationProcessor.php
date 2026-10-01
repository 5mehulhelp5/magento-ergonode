<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Ergonode\TemplateConsumer\Api\TemplateSynchronizationContributorInterface;
use Ergonode\TemplateConsumer\Model\Mapping\TemplateAttributeSetAutoMatcher;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetSynchronizer;
use InvalidArgumentException;

class TemplateSynchronizationProcessor
{
    /** @param TemplateSynchronizationContributorInterface[] $contributors */
    public function __construct(
        private readonly TemplateAttributeSetAutoMatcher $autoMatcher,
        private readonly TemplateAttributeSetSynchronizer $attributeSetSynchronizer,
        private readonly array $contributors = []
    ) {
        foreach ($contributors as $contributor) {
            if (!$contributor instanceof TemplateSynchronizationContributorInterface) {
                throw new InvalidArgumentException('Template synchronization contributors must implement their API.');
            }
        }
    }

    /**
     * @param string[] $templateCodes
     * @param string[] $deletedTemplateCodes
     */
    public function execute(array $templateCodes, array $deletedTemplateCodes): void
    {
        $this->autoMatcher->match($templateCodes);
        $this->attributeSetSynchronizer->sync($templateCodes);
        foreach ($this->contributors as $contributor) {
            $contributor->execute($templateCodes, $deletedTemplateCodes);
        }
    }
}
