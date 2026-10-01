<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Magento\Framework\Serialize\Serializer\Json;

class TemplateNormalizer
{
    public function __construct(private readonly Json $json)
    {
    }

    /**
     * @param array{code?: mixed, name?: mixed} $node
     * @return array{code: string, raw: array{code: string, name: array<int, array<string, mixed>>}, hash: string}
     */
    public function normalize(array $node): array
    {
        $code = trim((string)($node['code'] ?? ''));
        $names = isset($node['name']) && is_array($node['name'])
            ? array_values(array_filter($node['name'], 'is_array'))
            : [];
        $raw = ['code' => $code, 'name' => $names];

        return [
            'code' => $code,
            'raw' => $raw,
            'hash' => hash('sha256', $this->json->serialize($raw)),
        ];
    }
}
