<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationResultInterface;

interface CategoryReconciliationServiceInterface
{
    /**
     * Preview matches the latest local source snapshot; apply refreshes the complete source tree first.
     *
     * @param CategoryReconciliationRequestInterface $request
     * @return CategoryReconciliationResultInterface
     */
    public function execute(CategoryReconciliationRequestInterface $request): CategoryReconciliationResultInterface;
}
