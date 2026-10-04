<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

class BatchContext
{
    /** @var array<int, string> */
    public array $before = [];
    /** @var array<int, Closure> Media intentions, executed only in the media processor. */
    public array $media = [];
    /** @var array<string, mixed> Extension data shared by the three phases. */
    public array $data = [];
    public ?BatchEntry $current = null;

    /** @param list<BatchEntry> $entries */
    public function __construct(public readonly array $entries, private readonly LoggerInterface $logger)
    {
    }

    public function run(BatchEntry $entry, string $stage, callable $operation): void
    {
        if ($entry->error !== null) {
            return;
        }
        $previous = $this->current;
        $this->current = $entry;
        try {
            $operation();
        } catch (Throwable $error) {
            $this->fail($entry, $stage, $error);
        } finally {
            $this->current = $previous;
        }
    }

    public function fail(BatchEntry $entry, string $stage, Throwable $error): void
    {
        if ($entry->error !== null) {
            return;
        }
        $entry->error = $error;
        $entry->failedStage = $stage;
        $entry->logReference = bin2hex(random_bytes(6));
        $this->logger->error('Unable to synchronize Ergonode product batch item.', [
            'product_id' => $entry->productId, 'ergonode_sku' => $entry->sku(),
            'stage' => $stage, 'reference' => $entry->logReference, 'exception' => $error,
        ]);
    }

    /** One shared infrastructure failure gets one diagnostic, with the affected batch listed. */
    public function failBatch(string $stage, Throwable $error): void
    {
        $reference = bin2hex(random_bytes(6));
        $this->logger->error('Unable to finish Ergonode product batch phase.', [
            'product_ids' => $this->productIds(),
            'ergonode_skus' => array_map(static fn(BatchEntry $entry): string => $entry->sku(), $this->entries),
            'stage' => $stage, 'reference' => $reference, 'exception' => $error,
        ]);
        foreach ($this->entries as $entry) {
            if ($entry->error === null) {
                $entry->error = $error;
                $entry->failedStage = $stage;
                $entry->logReference = $reference;
            }
        }
    }

    /** @return list<int> */
    public function productIds(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(BatchEntry $entry): ?int => $entry->productId, $this->entries
        ))));
    }
}
