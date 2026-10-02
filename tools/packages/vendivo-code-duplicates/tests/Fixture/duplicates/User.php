<?php

declare(strict_types=1);

/** @param array<string, mixed> $user */
function processUser(array $user): int
{
    if ($user === []) {
        return 0;
    }

    return count($user);
}
