<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use InvalidArgumentException;

class AttributeSyncPlanner
{
    public function __construct(private readonly AttributeMutationFactory $mutations)
    {
    }

    /** @return string[] */
    public function getReadLanguages(AttributeStateInterface $desired, string $mode): array
    {
        if ($mode === AttributeSynchronizerInterface::MODE_RECONCILE) {
            return [];
        }
        $names = $desired->getNames();
        foreach ($desired->getOptions() as $option) {
            $names += $option->getNames();
        }

        return array_keys($names);
    }

    public function plan(
        AttributeStateInterface $desired,
        ?AttributeStateInterface $remote,
        string $mode
    ): AttributeSyncPlan {
        if (!$this->mutations->supports($desired->getType())) {
            return new AttributeSyncPlan(
                AttributeSyncPlan::STATUS_UNSUPPORTED,
                [],
                'Unsupported attribute type: ' . $desired->getType()
            );
        }
        if ($remote === null) {
            try {
                return new AttributeSyncPlan(AttributeSyncPlan::STATUS_READY, [[$this->mutations->create($desired)]]);
            } catch (InvalidArgumentException $exception) {
                return new AttributeSyncPlan(AttributeSyncPlan::STATUS_UNSUPPORTED, [], $exception->getMessage());
            }
        }
        if ($desired->getType() !== $remote->getType() || $desired->getScope() !== $remote->getScope()) {
            return new AttributeSyncPlan(
                AttributeSyncPlan::STATUS_CONFLICT,
                [],
                'Attribute type and scope are immutable.'
            );
        }

        $definition = [];
        if (!$this->namesMatch($desired->getNames(), $remote->getNames(), $mode)) {
            $definition[] = $this->mutations->setName($desired);
        }
        foreach ($desired->getParameters() as $name => $value) {
            if (!array_key_exists($name, $remote->getParameters())) {
                return new AttributeSyncPlan(
                    AttributeSyncPlan::STATUS_UNSUPPORTED,
                    [],
                    sprintf('Remote parameter "%s" is not verifiable for type "%s".', $name, $desired->getType())
                );
            }
            if ($remote->getParameters()[$name] !== $value) {
                if (in_array($desired->getType() . ':' . $name, ['numeric:unique', 'text:unique'], true)) {
                    return new AttributeSyncPlan(
                        AttributeSyncPlan::STATUS_UNSUPPORTED,
                        [],
                        sprintf('Attribute parameter "%s" is immutable for type "%s".', $name, $desired->getType())
                    );
                }
                $definition[] = $this->mutations->setParameter($desired->getCode(), $desired->getType(), $name, $value);
            }
        }
        $metadataAdd = array_diff_assoc($desired->getMetadata(), $remote->getMetadata());
        if ($metadataAdd !== []) {
            $definition[] = $this->mutations->addMetadata($desired->getCode(), $metadataAdd);
        }
        if ($mode === AttributeSynchronizerInterface::MODE_RECONCILE) {
            $metadataDelete = array_keys(array_diff_key($remote->getMetadata(), $desired->getMetadata()));
            if ($metadataDelete !== []) {
                $definition[] = $this->mutations->deleteMetadata($desired->getCode(), $metadataDelete);
            }
        }
        if ($definition !== []) {
            return new AttributeSyncPlan(AttributeSyncPlan::STATUS_READY, [$definition]);
        }

        if (in_array($desired->getType(), ['select', 'multi_select'], true)) {
            $stage = $this->nextOptionStage($desired, $remote, $mode);
            if ($stage !== []) {
                return new AttributeSyncPlan(AttributeSyncPlan::STATUS_READY, [$stage]);
            }
        }

        return new AttributeSyncPlan(AttributeSyncPlan::STATUS_NOOP);
    }

    /** @return \Ergonode\Publisher\Api\Data\MutationOperationInterface[] */
    private function nextOptionStage(
        AttributeStateInterface $desired,
        AttributeStateInterface $remote,
        string $mode
    ): array {
        $desiredByCode = $this->byCode($desired->getOptions());
        $remoteByCode = $this->byCode($remote->getOptions());
        $upserts = [];
        foreach ($desiredByCode as $code => $option) {
            if (!isset($remoteByCode[$code])) {
                $upserts[] = $this->mutations->addOption($desired->getCode(), $desired->getType(), $option);
            } elseif (!$this->namesMatch($option->getNames(), $remoteByCode[$code]->getNames(), $mode)) {
                $upserts[] = $this->mutations->renameOption($desired->getCode(), $desired->getType(), $option);
            }
        }
        if ($upserts !== []) {
            return $upserts;
        }
        if ($mode === AttributeSynchronizerInterface::MODE_RECONCILE) {
            $deletes = [];
            foreach (array_diff_key($remoteByCode, $desiredByCode) as $option) {
                $deletes[] = $this->mutations->deleteOption(
                    $desired->getCode(),
                    $desired->getType(),
                    $option->getCode()
                );
            }
            if ($deletes !== []) {
                return $deletes;
            }
        }
        $desiredOrder = array_keys($desiredByCode);
        $remoteOrder = array_values(array_intersect(array_keys($remoteByCode), $desiredOrder));
        if ($desiredOrder === $remoteOrder || $mode !== AttributeSynchronizerInterface::MODE_RECONCILE) {
            return [];
        }

        return [$this->mutations->setOptions($desired->getCode(), $desired->getType(), $desired->getOptions())];
    }

    /** @param array<string, string> $desired @param array<string, string> $remote */
    private function namesMatch(array $desired, array $remote, string $mode): bool
    {
        if ($mode !== AttributeSynchronizerInterface::MODE_RECONCILE) {
            $remote = array_intersect_key($remote, $desired);
        }

        return $desired === $remote;
    }

    /** @param AttributeOptionStateInterface[] $options @return array<string, AttributeOptionStateInterface> */
    private function byCode(array $options): array
    {
        $result = [];
        foreach ($options as $option) {
            $result[$option->getCode()] = $option;
        }
        return $result;
    }
}
