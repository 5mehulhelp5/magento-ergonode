<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\File;

use Ergonode\Media\Model\File\FileUsageRecorder;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Ergonode\Media\Model\ValueObject\File\FileUsageReference;
use Ergonode\Media\Model\ValueObject\File\FileUsageSet;
use PHPUnit\Framework\TestCase;

class FileUsageRecorderTest extends TestCase
{
    public function testConvertsOwnedValueObjectsOnlyAtPersistenceBoundary(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::once())->method('replaceFileUsages')->with(23, [[
            'source_path' => '/manual.pdf',
            'attribute_code' => 'instruction_file',
            'store_id' => 2,
        ]]);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::once())->method('dispatch');

        (new FileUsageRecorder($repository, $publisher))->synchronize(23, new FileUsageSet([
            new FileUsageReference('/manual.pdf', 'instruction_file', 2),
        ]));
    }
}
