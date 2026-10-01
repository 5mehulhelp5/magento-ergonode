<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Psr\Log\LoggerInterface;

class ProductMutationFailureReporter
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function describe(MutationResultInterface $result): string
    {
        $operation = $result->getOperation();
        $metadata = $operation->getMetadata();
        $reference = bin2hex(random_bytes(6));
        $errors = array_map($this->errorDetails(...), $result->getErrors());
        $context = [
            'reference' => $reference,
            'operation' => $operation->getField(),
            'operation_key' => $metadata['operation_key'] ?? '',
            'sku' => $metadata['entity_sku'] ?? '',
            'attribute' => $metadata['attribute_code'] ?? '',
            'language' => $metadata['language'] ?? '',
            'status' => $result->getStatus(),
            'alias' => $result->getAlias(),
            'attempts' => $result->getAttempts(),
            'errors' => $errors,
        ];
        $this->logger->error('Ergonode product publication operation failed.', $context);

        $parts = [(string)__('Ergonode operation "%1" failed.', $operation->getField())];
        if ($context['sku'] !== '') {
            $parts[] = (string)__('Product SKU: %1.', $context['sku']);
        }
        if ($context['attribute'] !== '') {
            $parts[] = (string)__('Attribute: %1.', $context['attribute']);
        }
        if ($context['language'] !== '') {
            $parts[] = (string)__('Language: %1.', $context['language']);
        }
        foreach ($errors as $error) {
            $parts[] = $this->errorMessage($error);
        }
        if ($errors === []) {
            $parts[] = (string)__('Ergonode returned no error details.');
        }
        $parts[] = (string)__('Result: %1. Log reference: %2.', $result->getStatus(), $reference);

        return implode(' ', array_unique($parts));
    }

    /** @param array<string, mixed> $error @return array<string, string> */
    private function errorDetails(array $error): array
    {
        $extensions = is_array($error['extensions'] ?? null) ? $error['extensions'] : [];
        $path = is_array($error['path'] ?? null) ? $error['path'] : [];

        return array_filter([
            'message' => $this->text($error['message'] ?? null),
            'code' => $this->text($extensions['code'] ?? null),
            'http_status' => $this->text($extensions['http_status'] ?? null),
            'failure_type' => $this->text($extensions['failure_type'] ?? null),
            'path' => implode('.', array_filter(array_map($this->text(...), $path), 'strlen')),
        ], 'strlen');
    }

    /** @param array<string, string> $error */
    private function errorMessage(array $error): string
    {
        $message = $error['message'] ?? '';
        if ($message === '' || in_array(strtolower($message), [
            'an unknown error occurred.', 'an unknown error occurred', 'internal server error',
        ], true)) {
            $message = (string)__('Ergonode returned an unspecified error. The API did not provide its cause.');
        }
        $parts = [$message];
        $labels = ['code' => 'Code', 'http_status' => 'HTTP', 'failure_type' => 'Type', 'path' => 'Path'];
        foreach ($labels as $key => $label) {
            if (isset($error[$key])) {
                $parts[] = $label . ': ' . $error[$key] . '.';
            }
        }

        return implode(' ', $parts);
    }

    private function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string)$value) : '';
    }
}
