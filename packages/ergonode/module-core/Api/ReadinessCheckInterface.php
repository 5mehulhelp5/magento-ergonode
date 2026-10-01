<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Ergonode\Core\Api\Data\ReadinessContextInterface;
use Ergonode\Core\Api\Data\ReadinessIssueInterface;

interface ReadinessCheckInterface
{
    /** @return string */
    public function getCode(): string;

    /** @return string */
    public function getDomain(): string;

    /**
     * @param string $operation
     * @return bool
     */
    public function supports(string $operation): bool;

    /**
     * @param ReadinessContextInterface $context
     * @return ReadinessIssueInterface[]
     */
    public function check(ReadinessContextInterface $context): array;
}
