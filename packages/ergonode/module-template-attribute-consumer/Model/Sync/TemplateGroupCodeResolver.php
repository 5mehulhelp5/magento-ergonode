<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Magento\Framework\Exception\LocalizedException;

class TemplateGroupCodeResolver
{
    private const int MAX_CODE_LENGTH = 255;
    private const array HASH_PREFIX_LENGTHS = [8, 12, 16, 24, 32];

    public function __construct(
        private readonly TemplateStructureNamer $namer,
        private readonly TemplateGroupCodeOwnerProvider $ownerProvider
    ) {
    }

    /**
     * @param array<string, true> $reservedGroupCodes
     * @param array<int, true> $releasedAttributeGroupIds
     * @throws LocalizedException
     */
    public function resolve(
        string $templateCode,
        int $attributeSetId,
        string $sectionCode,
        ?int $mappedAttributeGroupId = null,
        array $reservedGroupCodes = [],
        array $releasedAttributeGroupIds = []
    ): string {
        $baseCode = $this->namer->buildGroupCode($sectionCode);
        if ($this->isAvailable(
            $templateCode,
            $attributeSetId,
            $sectionCode,
            $baseCode,
            $mappedAttributeGroupId,
            $reservedGroupCodes,
            $releasedAttributeGroupIds
        )) {
            return $baseCode;
        }

        $hash = hash('sha256', $sectionCode);
        foreach (self::HASH_PREFIX_LENGTHS as $hashLength) {
            $candidate = $this->buildHashedCode($baseCode, $hash, $hashLength);
            if ($this->isAvailable(
                $templateCode,
                $attributeSetId,
                $sectionCode,
                $candidate,
                $mappedAttributeGroupId,
                $reservedGroupCodes,
                $releasedAttributeGroupIds
            )) {
                return $candidate;
            }
        }

        throw new LocalizedException(__(
            'Unable to resolve a unique Magento attribute group code for Ergonode section "%1" in attribute set %2.',
            $sectionCode,
            $attributeSetId
        ));
    }

    /**
     * @param array<string, true> $reservedGroupCodes
     * @param array<int, true> $releasedAttributeGroupIds
     */
    private function isAvailable(
        string $templateCode,
        int $attributeSetId,
        string $sectionCode,
        string $candidate,
        ?int $mappedAttributeGroupId,
        array $reservedGroupCodes,
        array $releasedAttributeGroupIds
    ): bool {
        if (isset($reservedGroupCodes[$candidate])) {
            return false;
        }

        $owner = $this->ownerProvider->find($attributeSetId, $candidate);
        if ($owner === null) {
            return true;
        }
        if (isset($releasedAttributeGroupIds[$owner['attribute_group_id']])) {
            return true;
        }
        if ($mappedAttributeGroupId !== null) {
            return $owner['attribute_group_id'] === $mappedAttributeGroupId;
        }

        return $owner['template_code'] === $templateCode
            && $owner['section_code'] === $sectionCode;
    }

    private function buildHashedCode(string $baseCode, string $hash, int $hashLength): string
    {
        $suffix = '_' . substr($hash, 0, $hashLength);

        return substr($baseCode, 0, self::MAX_CODE_LENGTH - strlen($suffix)) . $suffix;
    }
}
