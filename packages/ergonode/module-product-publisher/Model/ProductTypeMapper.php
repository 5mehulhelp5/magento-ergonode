<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\ProductTypeMapperInterface;
use Magento\Framework\Exception\LocalizedException;
use InvalidArgumentException;

class ProductTypeMapper implements ProductTypeMapperInterface
{
    /** @var array<string, string> */
    private array $typeMap;

    /** @param array<string, string> $types */
    public function __construct(array $types = ['simple' => 'simple', 'virtual' => 'simple'])
    {
        $allowedTypes = [
            ProductStateInterface::TYPE_SIMPLE,
            ProductStateInterface::TYPE_VARIABLE,
            ProductStateInterface::TYPE_GROUPING,
        ];
        $this->typeMap = [];
        foreach ($types as $magentoType => $ergonodeType) {
            if (trim($magentoType) === '' || !in_array($ergonodeType, $allowedTypes, true)) {
                throw new InvalidArgumentException('Product publisher type mappings are invalid.');
            }
            $this->typeMap[trim($magentoType)] = $ergonodeType;
        }
    }

    public function map(string $magentoTypeId): string
    {
        $type = $this->typeMap[$magentoTypeId] ?? null;
        if ($type === null) {
            throw new LocalizedException(__(
                'Magento product type "%1" is not supported for Ergonode publication.',
                $magentoTypeId
            ));
        }

        return $type;
    }
}
