<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;

interface AttributeStateLoaderInterface
{
    /**
     * Read the remote attribute definition using the publication credentials.
     *
     * @param string $code
     * @param string[] $languages
     * @return AttributeStateInterface|null
     */
    public function load(string $code, array $languages = []): ?AttributeStateInterface;
}
