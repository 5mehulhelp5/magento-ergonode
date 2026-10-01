<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\Set;
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\FilterManager;
use Throwable;

class AttributeSetManager implements ProductAttributeSetProviderInterface
{
    public function __construct(
        private readonly AttributeSetResource $attributeSetResource,
        private readonly SetFactory $attributeSetFactory,
        private readonly AttributeSetRepositoryInterface $attributeSetRepository,
        private readonly FilterManager $filterManager,
        private readonly ChangeReport $changeReport
    ) {
    }

    /**
     * @return array{attribute_set_id: int, created: bool}
     * @throws LocalizedException
     */
    public function createOrGetForTemplate(string $templateCode): array
    {
        $attributeSetName = $this->buildAttributeSetNameForTemplate($templateCode);
        $existingId = $this->getExistingAttributeSetIdForTemplate($templateCode);
        if ($existingId !== null) {
            $this->changeReport->add(
                'template_attribute_set',
                $templateCode,
                ChangeReport::ACTION_UNCHANGED,
                'Magento attribute set already exists for Ergonode template.',
                [
                    'attribute_set_id' => $existingId,
                    'attribute_set_name' => $attributeSetName,
                ]
            );

            return [
                'attribute_set_id' => $existingId,
                'created' => false,
            ];
        }

        $model = $this->attributeSetFactory->create();
        $model->setData([
            Set::KEY_ENTITY_TYPE_ID => $this->getProductEntityTypeId(),
            Set::KEY_ATTRIBUTE_SET_NAME => $attributeSetName,
        ]);

        try {
            $model->validate();
            $this->attributeSetRepository->save($model);
            $model->initFromSkeleton($this->attributeSetResource->getDefaultProductAttributeSetId());
            $this->attributeSetRepository->save($model);
        } catch (Throwable $exception) {
            $this->deleteCreatedSetOnFailure($model);
            throw new LocalizedException(
                __(
                    'Unable to create Magento attribute set for Ergonode template "%1": %2',
                    $templateCode,
                    $exception->getMessage()
                )
            );
        }

        $attributeSetId = (int)$model->getAttributeSetId();
        $this->changeReport->add(
            'template_attribute_set',
            $templateCode,
            ChangeReport::ACTION_INSERTED,
            'Created Magento attribute set from default product skeleton.',
            [
                'attribute_set_id' => $attributeSetId,
                'attribute_set_name' => $attributeSetName,
            ]
        );

        return [
            'attribute_set_id' => $attributeSetId,
            'created' => true,
        ];
    }

    /**
     * @throws LocalizedException
     */
    public function validateProductAttributeSet(int $attributeSetId): void
    {
        if ($attributeSetId <= 0) {
            throw new LocalizedException(__('Magento attribute set ID is required.'));
        }

        if (!$this->attributeSetResource->productAttributeSetExists($attributeSetId)) {
            throw new LocalizedException(__('Magento product attribute set "%1" does not exist.', $attributeSetId));
        }
    }

    public function getProductEntityTypeId(): int
    {
        return $this->attributeSetResource->getProductEntityTypeId();
    }

    public function getExistingAttributeSetIdForTemplate(string $templateCode): ?int
    {
        return $this->findAttributeSetIdByName($this->buildAttributeSetNameForTemplate($templateCode));
    }

    public function buildAttributeSetNameForTemplate(string $templateCode): string
    {
        $name = trim($this->filterManager->stripTags($templateCode));
        $name = preg_replace('/\s+/', ' ', $name) ?: '';

        return mb_substr($name !== '' ? $name : 'Ergonode Template', 0, 255);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getProductAttributeSets(): array
    {
        return $this->attributeSetResource->getProductAttributeSets();
    }

    private function findAttributeSetIdByName(string $attributeSetName): ?int
    {
        return $this->attributeSetResource->findAttributeSetIdByName($attributeSetName);
    }

    private function deleteCreatedSetOnFailure(Set $model): void
    {
        if ((int)$model->getAttributeSetId() <= 0) {
            return;
        }

        try {
            $this->attributeSetRepository->delete($model);
        } catch (Throwable) {
            // Best effort rollback. The original exception is more useful to the caller.
        }
    }
}
