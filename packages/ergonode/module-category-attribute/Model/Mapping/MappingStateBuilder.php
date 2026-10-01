<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Api\MappingStateBuilderInterface;

class MappingStateBuilder implements MappingStateBuilderInterface
{
    public function __construct(private readonly AttributeTypeCompatibilityInterface $typeCompatibility)
    {
    }

    public function attributes(array $rows, array $source, array $target): array
    {
        $result = [];
        foreach ($rows as $row) {
            $leftCode = trim((string)($row['ergonode_attribute_code'] ?? ''));
            $rightCode = trim((string)($row['magento_attribute_code'] ?? ''));
            $left = $leftCode !== '' ? ($source[$leftCode] ?? $this->missing($leftCode)) : null;
            $right = $rightCode !== '' ? ($target[$rightCode] ?? $this->missing($rightCode)) : null;
            $tone = 'warning';
            if ($left !== null && $right !== null) {
                $tone = $this->typeCompatibility->canMapAttributes(
                    (string)($left['type'] ?? ''),
                    (string)($right['type'] ?? '')
                ) ? 'ok' : 'error';
            }
            $result[] = [
                'mapping_id' => (int)$row['mapping_id'], 'left' => $left, 'right' => $right, 'tone' => $tone,
            ];
        }

        return $result;
    }

    public function options(array $rows, array $source, array $target): array
    {
        $source = array_column($source, null, 'code');
        $target = array_column($target, null, 'code');
        $result = [];
        foreach ($rows as $row) {
            $leftCode = trim((string)($row['ergonode_option_code'] ?? ''));
            $rightCode = $row['magento_option_id'] !== null ? 'option_' . (string)$row['magento_option_id'] : '';
            $left = $leftCode !== '' ? ($source[$leftCode] ?? $this->missing($leftCode, 'option')) : null;
            $right = $rightCode !== '' ? ($target[$rightCode] ?? $this->missing($rightCode, 'option')) : null;
            $result[] = ['left' => $left, 'right' => $right, 'tone' => $left && $right ? 'ok' : 'warning'];
        }

        return $result;
    }

    /** @return array{label: string, code: string, scope: string, type: string, active: bool} */
    private function missing(string $code, string $type = ''): array
    {
        return ['label' => $code, 'code' => $code, 'scope' => 'missing', 'type' => $type, 'active' => true];
    }
}
