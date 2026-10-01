<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Model\Attribute;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use InvalidArgumentException;
use PackHauer\UnitAttribute\Api\UnitAttributeMetadataInterface;

class UnitAttributeMetadata implements UnitAttributeMetadataInterface
{
    /** @var array<string, array{name: string, symbol: string}|null> */
    private array $cache = [];

    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly Json $json
    ) {
    }

    public function get(string $attributeCode): ?array
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            throw new LocalizedException(__('Product attribute code is required to read unit metadata.'));
        }
        if (array_key_exists($attributeCode, $this->cache)) {
            return $this->cache[$attributeCode];
        }

        $attribute = $this->attributeRepository->get($attributeCode);
        if (!$attribute instanceof Attribute) {
            throw new LocalizedException(__(
                'Product attribute "%1" does not expose Magento catalog additional data.',
                $attributeCode
            ));
        }

        $additionalData = $this->decode($attribute->getData('additional_data'));
        $unit = $additionalData[self::ADDITIONAL_DATA_KEY] ?? null;
        if ($unit === null) {
            return $this->cache[$attributeCode] = null;
        }
        if (!is_array($unit)) {
            throw new LocalizedException(__('Unit metadata for product attribute "%1" is invalid.', $attributeCode));
        }

        $name = trim((string)($unit['name'] ?? ''));
        $symbol = trim((string)($unit['symbol'] ?? ''));
        if ($name === '' || $symbol === '') {
            throw new LocalizedException(__(
                'Unit metadata for product attribute "%1" requires a name and symbol.',
                $attributeCode
            ));
        }

        return $this->cache[$attributeCode] = ['name' => $name, 'symbol' => $symbol];
    }

    public function withUnit(?string $additionalData, string $name, string $symbol): string
    {
        $name = trim($name);
        $symbol = trim($symbol);
        if ($name === '' || $symbol === '') {
            throw new LocalizedException(__('Unit metadata requires a name and symbol.'));
        }

        $decoded = $this->decode($additionalData);
        $decoded[self::ADDITIONAL_DATA_KEY] = ['name' => $name, 'symbol' => $symbol];

        return $this->json->serialize($decoded);
    }

    public function withoutUnit(?string $additionalData): ?string
    {
        $decoded = $this->decode($additionalData);
        unset($decoded[self::ADDITIONAL_DATA_KEY]);

        return $decoded === [] ? null : $this->json->serialize($decoded);
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function decode(mixed $additionalData): array
    {
        if ($additionalData === null || trim((string)$additionalData) === '') {
            return [];
        }

        try {
            $decoded = $this->json->unserialize((string)$additionalData);
        } catch (InvalidArgumentException $exception) {
            throw new LocalizedException(__('Catalog attribute additional data must be valid JSON.'), $exception);
        }
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new LocalizedException(__('Catalog attribute additional data must be a JSON object.'));
        }

        return $decoded;
    }
}
