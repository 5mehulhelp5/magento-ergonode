<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Provider;

class OptionSnapshotCache
{
    private const int MAX_OPTIONS = 500;
    private const int MAX_BYTES = 1048576;

    /** @param array<string, list<array<string, mixed>>> $lists */
    public function canRetain(array $lists): bool
    {
        $count = 0;
        $bytes = 0;
        foreach ($lists as $options) {
            $count += count($options);
            if ($count > self::MAX_OPTIONS) {
                return false;
            }
            foreach ($options as $option) {
                foreach ($option as $value) {
                    foreach (is_array($value) ? $value : [$value] as $text) {
                        $bytes += strlen((string)$text);
                    }
                }
                if ($bytes > self::MAX_BYTES) {
                    return false;
                }
            }
        }
        return count($lists) <= self::MAX_OPTIONS;
    }

    /** @var array<string, list<array{code: string, labels: array<string, string>}>> */
    public array $definitions = [];

    /** @var array<string, list<array{label: string, code: string, scope: string, type: string, active: bool}>> */
    public array $options = [];

    public function reset(): void
    {
        $this->definitions = [];
        $this->options = [];
    }
}
