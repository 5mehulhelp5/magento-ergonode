<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Api\TemplateImportContributorInterface;
use Ergonode\TemplateConsumer\Api\TemplateLoaderInterface;
use InvalidArgumentException;

class ImportedTemplateProcessor
{
    /** @var TemplateImportContributorInterface[] */
    private array $contributors;

    /** @param TemplateImportContributorInterface[] $contributors */
    public function __construct(
        private readonly TemplateLoaderInterface $templateLoader,
        private readonly TemplateNormalizer $normalizer,
        private readonly TemplateCacheWriter $cacheWriter,
        private readonly ChangeReport $changeReport,
        array $contributors = []
    ) {
        foreach ($contributors as $contributor) {
            if (!$contributor instanceof TemplateImportContributorInterface) {
                throw new InvalidArgumentException(
                    'Template import contributors must implement their API contract.'
                );
            }
        }
        $this->contributors = array_values($contributors);
    }

    /**
     * @param string[] $templateCodes
     * @return array{imported: int, changed: int, unchanged: int}
     */
    public function process(array $templateCodes): array
    {
        $templates = [];
        foreach (array_values(array_unique($templateCodes)) as $templateCode) {
            $templateCode = trim($templateCode);
            if ($templateCode === '') {
                continue;
            }
            $template = $this->normalizer->normalize($this->templateLoader->load($templateCode));
            if ($template['code'] !== '') {
                $templates[] = $template;
            }
        }

        $results = $this->cacheWriter->save($templates);
        $processedCodes = array_column($templates, 'code');
        foreach ($this->contributors as $contributor) {
            $contributor->execute($processedCodes);
        }

        $stats = ['imported' => 0, 'changed' => 0, 'unchanged' => 0];
        foreach ($templates as $template) {
            $result = $results[$template['code']];
            $this->changeReport->add(
                'template',
                $template['code'],
                $result,
                match ($result) {
                    ChangeReport::ACTION_INSERTED => 'Inserted Ergonode template cache row.',
                    ChangeReport::ACTION_UPDATED => 'Updated Ergonode template cache row.',
                    default => 'Ergonode template cache row is unchanged.',
                },
                []
            );
            if ($result === ChangeReport::ACTION_UNCHANGED) {
                $stats['unchanged']++;
            } else {
                $stats['imported']++;
                $stats['changed']++;
            }
        }

        return $stats;
    }
}
