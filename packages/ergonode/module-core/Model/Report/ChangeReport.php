<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Report;

use Magento\Framework\Serialize\Serializer\Json;
use SplTempFileObject;
use Generator;
use Magento\Framework\Exception\LocalizedException;

class ChangeReport
{
    public const string ACTION_INSERTED = 'inserted';
    public const string ACTION_UPDATED = 'updated';
    public const string ACTION_UNCHANGED = 'unchanged';
    public const string ACTION_SKIPPED = 'skipped';
    public const string ACTION_ERROR = 'error';

    private SplTempFileObject $entries;

    public function __construct(
        private readonly Json $json
    ) {
        $this->reset();
    }

    /**
     * @param array<string, mixed> $details
     */
    public function add(
        string $entity,
        string $identifier,
        string $action,
        string $message = '',
        array $details = []
    ): void {
        $entry = [
            'entity' => $entity,
            'identifier' => $identifier,
            'action' => $action,
            'message' => $message,
            'details' => $details,
        ];
        $line = $this->json->serialize($entry) . "\n";
        $this->entries->fseek(0, SEEK_END);
        if ($this->entries->fwrite($line) !== strlen($line)) {
            throw new LocalizedException(__('Unable to buffer the synchronization report.'));
        }
    }

    public function reset(): void
    {
        $this->entries = new SplTempFileObject(2097152);
    }

    /**
     * @return array<int, array{
     *     entity: string,
     *     identifier: string,
     *     action: string,
     *     message: string,
     *     details: array<string, mixed>
     * }>
     */
    public function getEntries(bool $includeUnchanged = false): array
    {
        return iterator_to_array($this->iterateEntries($includeUnchanged), false);
    }

    /**
     * @return Generator<int, array{entity: string, identifier: string, action: string,
     *     message: string, details: array<string, mixed>}>
     */
    public function iterateEntries(bool $includeUnchanged = false): Generator
    {
        $buffer = $this->entries;
        $offset = 0;
        $buffer->fseek(0, SEEK_END);
        $end = $buffer->ftell();
        while ($offset < $end) {
            $buffer->fseek($offset);
            $line = $buffer->fgets();
            $offset = $buffer->ftell();
            $entry = $this->json->unserialize($line);
            if ($includeUnchanged || $entry['action'] !== self::ACTION_UNCHANGED) {
                yield $entry;
            }
        }
    }

    public function formatDetails(array $details): string
    {
        if (!$details) {
            return '';
        }

        $flat = [];
        foreach ($details as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $flat[] = sprintf('%s=%s', $key, (string)$value);
                continue;
            }

            $flat[] = sprintf('%s=%s', $key, $this->json->serialize($value));
        }

        return implode(' ', $flat);
    }
}
