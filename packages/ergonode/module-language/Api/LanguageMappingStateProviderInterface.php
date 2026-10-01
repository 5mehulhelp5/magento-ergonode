<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

use Ergonode\Language\Model\Data\MappingStateDto;

interface LanguageMappingStateProviderInterface
{
    /**
     * Reads a coherent, current database snapshot for editing and conflict detection.
     *
     * @return MappingStateDto
     */
    public function getState(): MappingStateDto;
}
