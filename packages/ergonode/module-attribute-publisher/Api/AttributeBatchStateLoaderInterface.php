<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;

interface AttributeBatchStateLoaderInterface
{
    /**
     * Read complete definitions and options with publication credentials, without retaining state between calls.
     * Missing definitions are null; incomplete responses and transport failures propagate to the caller.
     *
     * @param string[] $codes
     * @param string[] $languages Empty means all languages, as in AttributeStateLoaderInterface::load().
     * @return array<string, AttributeStateInterface|null> States keyed by requested code, in first-occurrence order.
     */
    public function loadBatch(array $codes, array $languages = []): array;
}
