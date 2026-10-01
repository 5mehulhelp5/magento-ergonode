<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionSnapshotInterface;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionCheckRecorderInterface;
use Throwable;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class AttributeDefinitionSynchronization implements AttributeDefinitionSynchronizationInterface
{
    private const string LOCK_NAME = 'ergonode_attribute_synchronization_batch';

    public function __construct(
        private readonly AttributeDefinitionLoader $loader,
        private readonly AttributeDefinitionSnapshotInterface $snapshot,
        private readonly ConfigProvider $config,
        private readonly LockManagerInterface $lock,
        private readonly ChangeReport $report,
        private readonly AttributeDefinitionCheckRecorderInterface $checkRecorder,
        private readonly LanguageStoreMappingProviderInterface $languages
    ) {
    }

    public function synchronize(bool $force = false, bool $writeScope = false): void
    {
        if (!$this->lock->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Attribute synchronization is already running.'));
        }
        try {
            $this->checkRecorder->record('running');
            $this->checkRecorder->record($this->refresh($force, $writeScope));
        } catch (Throwable $exception) {
            $this->checkRecorder->record('failed');
            throw $exception;
        } finally {
            $this->lock->unlock(self::LOCK_NAME);
        }
    }

    private function refresh(bool $force, bool $writeScope): string
    {
        $languages = array_values(array_unique($this->languages->getLanguageCodes()));
        sort($languages, SORT_STRING);
        $source = hash('sha256', $this->config->getEnvironment() . '|' . $this->config->getGraphQlUrl()
            . '|' . $this->config->getMode() . '|' . (int)$writeScope
            . '|' . json_encode($languages, JSON_THROW_ON_ERROR));
        $state = $this->snapshot->getState();
        if (($state['source'] ?? null) !== $source) {
            $state = null;
        }
        $attributes = $this->loader->changes('attributeStream', $state['attribute'] ?? null, $writeScope);
        $deleted = $this->loader->changes('attributeDeletedStream', $state['deleted'] ?? null, $writeScope);
        if (!$force && $state !== null && !$attributes['changed'] && !$deleted['changed']) {
            return 'no_changes';
        }
        if ($state !== null && ($attributes['changed'] || $deleted['changed'])) {
            $this->checkRecorder->record('changes_detected');
        }
        $removed = $this->snapshot->replace($this->loader->load($writeScope), [
            'source' => $source, 'attribute' => $attributes['cursor'], 'deleted' => $deleted['cursor'],
        ]);
        foreach ($removed as $code) {
            $this->report->add(
                'attribute',
                $code,
                ChangeReport::ACTION_SKIPPED,
                'Ergonode attribute is no longer available. Snapshot removed; '
                . 'Magento definitions, values and mappings preserved.'
            );
        }

        return $state === null ? 'initialized' : (
            $attributes['changed'] || $deleted['changed'] ? 'changed' : 'no_changes'
        );
    }
}
