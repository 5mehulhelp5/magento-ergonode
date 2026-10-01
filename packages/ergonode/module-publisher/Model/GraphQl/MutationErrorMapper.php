<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\GraphQl;

class MutationErrorMapper
{
    private const array TRANSIENT_CODES = [
        'INTERNAL_SERVER_ERROR',
        'SERVICE_UNAVAILABLE',
        'TIMEOUT',
        'TOO_MANY_REQUESTS',
    ];

    /**
     * @param array<int, array<string, mixed>> $errors
     * @param string[] $knownAliases
     * @return array<int, array<string, mixed>>
     */
    public function forAlias(array $errors, string $alias, array $knownAliases): array
    {
        return array_values(array_filter(
            $errors,
            static function (array $error) use ($alias, $knownAliases): bool {
                $path = $error['path'] ?? null;

                if (!is_array($path) || !isset($path[0])) {
                    return true;
                }

                $root = (string)$path[0];

                return $root === $alias || !in_array($root, $knownAliases, true);
            }
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    public function isTransient(array $errors): bool
    {
        if ($errors === []) {
            return false;
        }

        foreach ($errors as $error) {
            $extensions = $error['extensions'] ?? null;
            $code = is_array($extensions) ? strtoupper((string)($extensions['code'] ?? '')) : '';
            $message = strtolower((string)($error['message'] ?? ''));

            if (!in_array($code, self::TRANSIENT_CODES, true)
                && !str_contains($message, 'timeout')
                && !str_contains($message, 'temporarily unavailable')
            ) {
                return false;
            }
        }

        return true;
    }
}
