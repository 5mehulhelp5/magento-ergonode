<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Ergonode\Core\Api\Exception\GraphQlRequestException;

interface GraphQlMutationClientInterface
{
    /**
     * Execute a mutation and preserve the complete GraphQL response envelope.
     *
     * @param string $document
     * @param array<string, mixed> $variables
     * @return array{data?: array<string, mixed>|null, errors?: array<int, array<string, mixed>>}
     * @throws GraphQlRequestException
     */
    public function mutateWithResponse(string $document, array $variables = []): array;
}
