<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface AttributeAvailabilityInterface
{
    /** @return string[] Codes in the last successfully reconciled definition snapshot. */
    public function getCodes(): array;
}
