<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use IteratorAggregate;
use Magento\Framework\Exception\LocalizedException;
use SplTempFileObject;
use Traversable;

/**
 * Holds validated definitions on temporary storage before the database transaction starts.
 * @implements IteratorAggregate<int, array{code: string, type: string, scope: string, labels: array<string,
 *     string>, parameters: array<string, bool|string>, hash: string}>
 */
class DefinitionBuffer implements IteratorAggregate
{
    private readonly SplTempFileObject $file;

    public function __construct()
    {
        $this->file = new SplTempFileObject(2097152);
    }

    /**
     * @param array{code: string, type: string, scope: string, labels: array<string, string>,
     *     parameters: array<string, bool|string>, hash: string} $definition
     */
    public function append(array $definition): void
    {
        $line = json_encode($definition, JSON_THROW_ON_ERROR) . "\n";
        if ($this->file->fwrite($line) !== strlen($line)) {
            throw new LocalizedException(__('Unable to buffer the attribute snapshot.'));
        }
    }

    /**
     * @return Traversable<int, array{code: string, type: string, scope: string, labels: array<string, string>,
     *     parameters: array<string, bool|string>, hash: string}>
     */
    public function getIterator(): Traversable
    {
        $this->file->rewind();
        while (!$this->file->eof()) {
            $line = $this->file->fgets();
            if ($line !== '') {
                yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        }
    }
}
