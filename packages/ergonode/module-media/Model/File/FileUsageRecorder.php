<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\File;

use Ergonode\Media\Api\FileUsageRecorderInterface;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Ergonode\Media\Model\ValueObject\File\FileUsageSet;

class FileUsageRecorder implements FileUsageRecorderInterface
{
    public function __construct(
        private readonly MediaRepository $repository,
        private readonly QueuePublisher $publisher
    ) {
    }

    public function synchronize(int $productId, FileUsageSet $references): void
    {
        $this->repository->replaceFileUsages(
            $productId,
            $references->toRows(),
            $references->attributeCodes(),
            $references->preservedAttributeCodes()
        );
        $this->publisher->dispatch();
    }
}
