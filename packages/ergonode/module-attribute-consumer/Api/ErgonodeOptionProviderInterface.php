<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface ErgonodeOptionProviderInterface
{
    /**
     * @param  string $attributeCode
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    public function getOptions(string $attributeCode): array;

    /**
     * @param  string $attributeCode
     * @return array<int, array{code: string, labels: array<string, string>}>
     */
    public function getOptionDefinitions(string $attributeCode): array;
    /**
     * Read ordered definitions for a set of attributes, including empty lists for missing codes.
     * @param string[] $attributeCodes
     * @return array<string, list<array{code: string, labels: array<string, string>}>>
     */
    public function getOptionDefinitionsByCodes(array $attributeCodes): array;

    /**
     * Stream snapshot rows in storage order without retaining the complete option set.
     *
     * @param string[] $attributeCodes
     * @return iterable<array{attribute_code: string, code: string, sort_order: int, labels: array<string, string>}>
     */
    public function iterateOptionDefinitions(array $attributeCodes): iterable;

    /**
     * Return snapshot option totals for the requested attributes.
     *
     * @param  string[] $attributeCodes
     * @return array<string, int>
     */
    public function getOptionCounts(array $attributeCodes): array;
}
