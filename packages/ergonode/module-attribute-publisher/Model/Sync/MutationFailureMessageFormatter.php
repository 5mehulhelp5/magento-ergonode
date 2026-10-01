<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\Publisher\Api\Data\MutationResultInterface;

class MutationFailureMessageFormatter
{
    public function format(MutationResultInterface $result, string $subject): string
    {
        $errors = $result->getErrors();
        $error = is_array($errors[0] ?? null) ? $errors[0] : [];
        $message = trim((string)($error['message'] ?? ''));
        $extensions = is_array($error['extensions'] ?? null) ? $error['extensions'] : [];
        $code = strtoupper(trim((string)($extensions['code'] ?? '')));

        if ($message !== '' && $code !== '') {
            return sprintf('[%s] %s', $code, $message);
        }

        return $message !== '' ? $message : $subject . ' mutation failed with status ' . $result->getStatus() . '.';
    }
}
