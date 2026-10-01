<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Model;

use Ergonode\ProductAdminUi\Api\GridActionProviderInterface;
use Magento\Framework\Module\Manager;

class GridActionPool
{
    /** @param array<string, array{modules: string[], provider: GridActionProviderInterface}> $actions */
    public function __construct(private readonly Manager $modules, private readonly array $actions = [])
    {
    }

    /** @return list<array<string, mixed>> */
    public function getActions(): array
    {
        $result = [];
        foreach ($this->actions as $code => $action) {
            foreach ($action['modules'] as $module) {
                if (!$this->modules->isEnabled($module)) {
                    continue 2;
                }
            }
            $configuration = $action['provider']->getConfiguration();
            if ($configuration !== null) {
                $result[] = ['code' => $code, ...$configuration];
            }
        }

        return $result;
    }
}
