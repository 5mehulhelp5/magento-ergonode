<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingSaveNoticeCollectorInterface;

class MappingSaveNoticeCollector implements MappingSaveNoticeCollectorInterface
{
    /**
     * @var string[]
     */
    private array $warnings = [];

    public function addWarning(string $message): void
    {
        $message = trim($message);
        if ($message !== '' && !in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    public function consumeWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }
}
