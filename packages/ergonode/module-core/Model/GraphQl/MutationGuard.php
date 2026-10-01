<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\Core\Model\Update\UpdateGuard;

class MutationGuard
{
    public function __construct(
        private readonly UpdateGuard $updateGuard
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function authorize(string $document): string
    {
        $normalized = ltrim(preg_replace('/^\s*#.*$/m', '', $document) ?? $document);

        if (!preg_match('/^mutation\b/i', $normalized)) {
            throw new GraphQlRequestException(
                (string)__('Only GraphQL mutation operations are allowed on the update path.'),
                GraphQlRequestException::FAILURE_REQUEST_CONSTRUCTION
            );
        }

        return $this->authorizeUpdateScope();
    }

    /**
     * @throws LocalizedException
     */
    public function authorizeUpdateScope(): string
    {
        try {
            return $this->updateGuard->authorize();
        } catch (GraphQlRequestException $exception) {
            throw $exception;
        } catch (LocalizedException $exception) {
            throw new GraphQlRequestException(
                $exception->getMessage(),
                GraphQlRequestException::FAILURE_AUTHORIZATION
            );
        }
    }
}
