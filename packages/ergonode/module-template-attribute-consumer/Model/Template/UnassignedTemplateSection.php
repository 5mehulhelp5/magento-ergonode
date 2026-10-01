<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Template;

class UnassignedTemplateSection
{
    public const string CODE = '__internal_unassigned_attributes__';
    public const string GROUP_NAME = 'Ergonode - Unassigned';
    public const array RAW = [
        'code' => self::CODE,
        'attributeList' => ['edges' => []],
    ];
}
