<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Model\Mapping;

use Ergonode\Template\Api\TemplateMappingSaverInterface;
use Ergonode\TemplateAdminUi\Model\MappingVisibility;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class WorkspaceMappingSaver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly TemplateMappingSaverInterface $mappingSaver,
        private readonly MappingVisibility $mappingVisibility
    ) {
    }

    /**
     * @param array<string, int|string|null> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array<string, int>
     */
    public function save(array $mappings, array $visibility): array
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $stats = $this->mappingSaver->save($mappings);
            $this->mappingVisibility->save($visibility);
            $connection->commit();

            return $stats;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
