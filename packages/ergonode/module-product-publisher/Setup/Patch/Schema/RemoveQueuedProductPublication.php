<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Setup\Patch\Schema;

use Ergonode\ProductPublisher\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

/** Deployment requires a database backup: retired publication history cannot be reconstructed. */
class RemoveQueuedProductPublication implements SchemaPatchInterface
{
    public function __construct(
        private readonly SchemaSetupInterface $schemaSetup,
        private readonly RuntimeRecordCleaner $cleaner
    ) {
    }

    public function apply(): self
    {
        $this->cleaner->execute($this->schemaSetup);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [DropLegacyCatalogPublisherTables::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
