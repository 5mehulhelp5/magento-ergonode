<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Api\TemplateStructureLoaderInterface;
use Ergonode\TemplateConsumer\Api\TemplateImportContributorInterface;

class ImportedTemplateProcessor implements TemplateImportContributorInterface
{
    public function __construct(
        private readonly TemplateStructureLoaderInterface $templateStructureLoader,
        private readonly TemplateStructureNormalizer $normalizer,
        private readonly TemplateStructureCacheWriter $cacheWriter,
        private readonly ChangeReport $changeReport
    ) {
    }

    /** @param string[] $templateCodes */
    public function execute(array $templateCodes): void
    {
        $templates = [];
        foreach (array_values(array_unique($templateCodes)) as $templateCode) {
            $templateCode = trim($templateCode);
            if ($templateCode === '') {
                continue;
            }

            $template = $this->normalizer->normalize($this->templateStructureLoader->load($templateCode));
            if ($template['code'] !== '') {
                $templates[] = $template;
            }
        }

        $results = $this->cacheWriter->save($templates);

        foreach ($templates as $template) {
            $result = $results[$template['code']];
            $this->changeReport->add(
                'template_structure',
                $template['code'],
                $result,
                match ($result) {
                    ChangeReport::ACTION_INSERTED => 'Inserted Ergonode template structure cache.',
                    ChangeReport::ACTION_UPDATED => 'Updated Ergonode template structure cache.',
                    default => 'Ergonode template structure cache is unchanged.',
                },
                ['sections' => count($template['sections'])]
            );
        }
    }
}
