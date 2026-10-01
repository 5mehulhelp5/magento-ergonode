<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Sync;

class OptionPairPlanner
{
    public function __construct(private readonly OptionMatchKeyResolver $keys)
    {
    }

    /**
     * @param array<string, mixed> $mapping
     * @param array<int|string, array<string, mixed>> $ergonodeOptions
     * @param array<int, array<string, mixed>> $magentoOptions
     * @param array<int, string> $storeLanguages
     * @param array<string, int> $savedPairs Ergonode code to Magento option ID.
     * @return array{matches: array<int, array{left: array<string, mixed>, right: array<string, mixed>}>,
     *     conflicts: array<string, string>, unmatched: array<int, string>}
     */
    public function plan(
        array $mapping,
        array $ergonodeOptions,
        array $magentoOptions,
        array $storeLanguages = [],
        array $savedPairs = []
    ): array {
        $left = $this->indexOptions($ergonodeOptions);
        $right = $this->indexOptions($magentoOptions);
        $usedLeft = array_fill_keys(array_keys($savedPairs), true);
        $usedRight = [];
        foreach ($savedPairs as $optionId) {
            $usedRight['option_' . $optionId] = true;
        }
        $matches = [];
        $conflicts = [];

        $this->matchPass($mapping, $left, $right, null, $usedLeft, $usedRight, $matches, $conflicts);
        ksort($storeLanguages, SORT_NUMERIC);
        foreach ($storeLanguages as $storeId => $language) {
            if ((int)$storeId === 0 || $this->allResolved($left, $usedLeft, $conflicts)) {
                continue;
            }
            $this->matchPass(
                $mapping,
                $left,
                $right,
                [(int)$storeId, (string)$language],
                $usedLeft,
                $usedRight,
                $matches,
                $conflicts
            );
        }

        $unmatched = [];
        foreach ($left as $code => $_) {
            if (!isset($usedLeft[$code]) && !isset($conflicts[$code])) {
                $unmatched[] = (string)$code;
            }
        }

        return ['matches' => $matches, 'conflicts' => $conflicts, 'unmatched' => $unmatched];
    }

    /**
     * @param array<string, array<string, mixed>> $left
     * @param array<string, bool> $usedLeft
     * @param array<string, string> $conflicts
     */
    private function allResolved(array $left, array $usedLeft, array $conflicts): bool
    {
        foreach ($left as $code => $_) {
            if (!isset($usedLeft[$code]) && !isset($conflicts[$code])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int|string, array<string, mixed>> $options @return array<string, array<string, mixed>> */
    private function indexOptions(array $options): array
    {
        $indexed = [];
        foreach ($options as $option) {
            $code = trim((string)($option['code'] ?? ''));
            if ($code !== '' && ($option['active'] ?? true) !== false) {
                $indexed[$code] = $option;
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $mapping
     * @param array<string, array<string, mixed>> $left
     * @param array<string, array<string, mixed>> $right
     * @param array{int, string}|null $store
     * @param array<string, bool> $usedLeft
     * @param array<string, bool> $usedRight
     * @param array<int, array{left: array<string, mixed>, right: array<string, mixed>}> $matches
     * @param array<string, string> $conflicts
     */
    private function matchPass(
        array $mapping,
        array $left,
        array $right,
        ?array $store,
        array &$usedLeft,
        array &$usedRight,
        array &$matches,
        array &$conflicts
    ): void {
        $leftByKey = [];
        $rightByKey = [];
        foreach ($left as $code => $option) {
            if (isset($usedLeft[$code]) || isset($conflicts[$code])) {
                continue;
            }
            $key = $this->leftKey($mapping, $option, $store);
            if ($key !== '') {
                $leftByKey[$key][] = $code;
            }
        }
        foreach ($right as $code => $option) {
            $key = $this->rightKey($mapping, $option, $store);
            if ($key !== '') {
                $rightByKey[$key][] = $code;
            }
        }
        foreach ($leftByKey as $key => $leftCodes) {
            $rightCodes = $rightByKey[$key] ?? [];
            if ($rightCodes === []) {
                continue;
            }
            if (count($leftCodes) !== 1 || count($rightCodes) !== 1 || isset($usedRight[$rightCodes[0]])) {
                foreach ($leftCodes as $code) {
                    $conflicts[$code] = 'Ambiguous option match; select the pair manually.';
                }
                continue;
            }
            $leftCode = $leftCodes[0];
            $rightCode = $rightCodes[0];
            $matches[] = ['left' => $left[$leftCode], 'right' => $right[$rightCode]];
            $usedLeft[$leftCode] = true;
            $usedRight[$rightCode] = true;
        }
    }

    /**
     * @param array<string, mixed> $mapping
     * @param array<string, mixed> $option
     * @param array{int, string}|null $store
     */
    private function leftKey(array $mapping, array $option, ?array $store): string
    {
        if ($store === null) {
            return $this->keys->resolvePrimary($mapping, $option, 'ergonode');
        }
        $name = (string)($option['names'][$store[1]] ?? '');

        return $name === ''
            ? ''
            : $this->keys->resolve($mapping, array_replace($option, ['label' => $name]), 'ergonode');
    }

    /**
     * @param array<string, mixed> $mapping
     * @param array<string, mixed> $option
     * @param array{int, string}|null $store
     */
    private function rightKey(array $mapping, array $option, ?array $store): string
    {
        if ($store === null) {
            return $this->keys->resolve($mapping, $option, 'magento');
        }
        $label = (string)($option['store_labels'][$store[0]] ?? '');

        return $label === ''
            ? ''
            : $this->keys->resolve($mapping, array_replace($option, ['label' => $label]), 'magento');
    }
}
