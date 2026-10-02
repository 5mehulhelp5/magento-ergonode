<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\Media\Model\Gallery\GalleryScheduler;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;
use PHPUnit\Framework\TestCase;

class GallerySchedulerTest extends TestCase
{
    public function testDoesNotScheduleGalleryWhenSynchronizationIsDisabled(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(false);
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::never())->method('replaceGallery');
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');

        (new GalleryScheduler($configuration, $repository, $publisher))->schedule(
            23,
            new GallerySelection(['/first.jpg'])
        );
    }

    public function testSchedulesReservedGalleryAttributeWhenSynchronizationIsEnabled(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::once())
            ->method('replaceGallery')
            ->with(23, ['/first.jpg', '/second.jpg']);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::once())->method('dispatch');

        (new GalleryScheduler($configuration, $repository, $publisher))->schedule(
            23,
            new GallerySelection(['/first.jpg', '/second.jpg', '/first.jpg'])
        );
    }

    public function testDoesNotClearGalleryWhenEnabledSnapshotDoesNotContainGalleryAttribute(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::never())->method('replaceGallery');
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');

        (new GalleryScheduler($configuration, $repository, $publisher))->schedule(23, null);
    }

    public function testClearsGalleryForPresentEmptySelection(): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::once())->method('replaceGallery')->with(23, []);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::once())->method('dispatch');

        (new GalleryScheduler($configuration, $repository, $publisher))->schedule(23, new GallerySelection([]));
    }
}
