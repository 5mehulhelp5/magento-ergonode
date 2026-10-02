<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Import;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\GraphQl\MultimediaClient;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MediaRepository;

class StreamScheduler
{
    public const string PROCESS_CODE = 'multimedia_stream';
    public function __construct(
        private readonly MultimediaClient $client,
        private readonly MediaRepository $repository,
        private readonly CursorStorage $cursors,
        private readonly QueuePublisher $publisher,
        private readonly MediaConfig $config,
        private readonly ConfigProvider $connection
    ) {
    }

    /**
     * Schedule multimedia stream pages.
     *
     * @return array{pages:int,assets:int,products:int}
     */
    public function schedule(int $maximumPages = 1): array
    {
        $summary = ['pages' => 0, 'assets' => 0, 'products' => 0];
        if (!$this->connection->isEnabled()) {
            return $summary;
        }

        $cursor = $this->cursors->get(self::PROCESS_CODE)['cursor'] ?? null;
        $pageLimit = max(1, $maximumPages);
        for ($i = 0; $i < $pageLimit; $i++) {
            $page = $this->client->stream($cursor, $this->config->getStreamPageSize());
            $products = $this->repository->recordStream($page['items']);
            if ($page['cursor'] !== null) {
                $this->cursors->save(self::PROCESS_CODE, $page['cursor']);
                $cursor = $page['cursor'];
            }
            $summary['pages']++;
            $summary['assets'] += count($page['items']);
            $summary['products'] += count($products);
            if (!$page['has_more']) {
                break;
            }
        }
        if ($summary['products'] > 0) {
            $this->publisher->dispatch();
        }

        return $summary;
    }
}
