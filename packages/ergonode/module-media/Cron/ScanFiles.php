<?php

declare(strict_types=1);

namespace Ergonode\Media\Cron;

use Ergonode\Media\Model\Index\LocalFileScanner;

class ScanFiles
{
    public function __construct(private readonly LocalFileScanner $scanner)
    {
    }

    public function execute(): void
    {
        $this->scanner->scan(true);
    }
}
