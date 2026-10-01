<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Request;

use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class MappingPayloadDecoder
{
    public function __construct(private readonly Json $json)
    {
    }

    /** @return array<string, mixed> */
    public function decode(string $payload): array
    {
        if ($payload === '') {
            throw new LocalizedException(__('Missing save payload.'));
        }
        try {
            $decoded = $this->json->unserialize($payload);
        } catch (InvalidArgumentException) {
            throw new LocalizedException(__('Invalid save payload.'));
        }
        if (!is_array($decoded)) {
            throw new LocalizedException(__('Invalid save payload.'));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    public function requireList(array $payload, string $key, string $message): array
    {
        if (!array_key_exists($key, $payload) || !is_array($payload[$key])) {
            throw new LocalizedException(__($message));
        }

        return $payload[$key];
    }
}
