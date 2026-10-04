<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use Ergonode\Media\Model\ResourceModel\MaterializationAudit;
use Psr\Log\LoggerInterface;
use Throwable;

/** Diagnose existing mappings without repairing files or changing expected content. */
class MaterializationIntegrityVerifier
{
    public function __construct(
        private readonly MaterializationAudit $references,
        private readonly LocalFiles $files,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, true> $unreadable
     * @param array<string, string> $verifiedHashes Hashes read in this traversal, never from a previous scan.
     * @return array{mismatched:int,missing:int,unreadable:int}
     */
    public function verify(array $unreadable = [], array $verifiedHashes = []): array
    {
        $counts = ['mismatched' => 0, 'missing' => 0, 'unreadable' => 0];
        $actual = $verifiedHashes;
        $reported = [];
        $after = 0;
        do {
            $page = $this->references->page($after);
            foreach ($page as $reference) {
                $after = $reference['id'];
                $path = $reference['path'];
                if (isset($unreadable[$path])) {
                    continue;
                }
                if (!array_key_exists($path, $actual)) {
                    try {
                        $stat = $this->files->stat($path);
                        $actual[$path] = $stat === null ? null : $this->files->hash($path);
                    } catch (Throwable $exception) {
                        $counts['unreadable']++;
                        $unreadable[$path] = true;
                        $this->logger->error('Unable to verify local media file.', [
                            'path' => $path, 'asset_id' => $reference['asset_id'],
                            'source_path' => $reference['source_path'], 'exception' => $exception,
                        ]);
                        continue;
                    }
                }
                $hash = $actual[$path];
                $key = $path . ':' . bin2hex($reference['content_hash']);
                if (isset($reported[$key]) || ($hash !== null && hash_equals($reference['content_hash'], $hash))) {
                    continue;
                }
                $reported[$key] = true;
                $reason = $hash === null ? 'missing' : 'mismatched';
                $counts[$reason]++;
                $this->logger->warning('Local media integrity check found a problem.', [
                    'reason' => $reason, 'path' => $path, 'asset_id' => $reference['asset_id'],
                    'source_path' => $reference['source_path'], 'product_id' => $reference['product_id'],
                    'expected_sha256' => bin2hex($reference['content_hash']),
                    'actual_sha256' => $hash === null ? null : bin2hex($hash),
                ]);
            }
        } while ($page !== []);

        return $counts;
    }
}
