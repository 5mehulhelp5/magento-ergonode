<?php

declare(strict_types=1);

/** @param array<string, mixed> $customer */
function processCustomer(array $customer): int
{
    if ($customer === []) {
        return 0;
    }

    return count($customer);
}
