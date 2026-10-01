<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Template\UnassignedTemplateSection;

class TemplateStructureNamer
{
    public function buildGroupCode(string $sectionCode): string
    {
        if ($sectionCode === CommonTemplateGroup::SECTION_CODE) {
            return CommonTemplateGroup::GROUP_CODE;
        }

        $code = strtolower(trim($sectionCode));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?: '';
        $code = trim($code, '_') ?: 'section';

        return substr('ergonode_' . $code, 0, 255);
    }

    public function buildGroupName(
        string $sectionCode,
        ?string $sectionName = null,
        bool $isSynthetic = false
    ): string {
        if ($sectionCode === CommonTemplateGroup::SECTION_CODE) {
            return CommonTemplateGroup::GROUP_NAME;
        }

        if ($isSynthetic) {
            return UnassignedTemplateSection::GROUP_NAME;
        }

        $code = trim($sectionCode);
        $name = trim((string)$sectionName);
        if ($name !== '') {
            return mb_substr('Ergonode - ' . $name, 0, 255);
        }
        if ($code === '') {
            return 'Ergonode - Section';
        }

        $name = preg_replace('/[_-]+/', ' ', $code) ?: $code;

        return mb_substr('Ergonode - ' . ucwords(strtolower(trim($name))), 0, 255);
    }
}
