<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;

class ProductDependencyLayerPlanner
{
    /**
     * Return dependency-first SKU layers. Mutually dependent products share
     * one layer so their stateless batch synchronizer can converge together.
     *
     * @param array<int|string, ProductStateInterface> $products
     * @return array<int, string[]>
     */
    public function plan(array $products): array
    {
        if ($products === []) {
            return [];
        }
        $graph = [];
        foreach ($products as $sku => $product) {
            $graph[$sku] = array_values(array_intersect($this->relatedSkus($product), array_keys($products)));
            sort($graph[$sku]);
        }
        [$components, $componentBySku] = $this->stronglyConnectedComponents($graph);
        $componentDependencies = array_fill(0, count($components), []);
        foreach ($graph as $sku => $dependencies) {
            $component = $componentBySku[$sku];
            foreach ($dependencies as $dependency) {
                $dependencyComponent = $componentBySku[$dependency];
                if ($dependencyComponent !== $component) {
                    $componentDependencies[$component][$dependencyComponent] = true;
                }
            }
        }

        $remaining = array_fill_keys(array_keys($components), true);
        $layers = [];
        while ($remaining !== []) {
            $readyComponents = [];
            foreach (array_keys($remaining) as $component) {
                if (array_intersect_key($componentDependencies[$component], $remaining) === []) {
                    $readyComponents[] = $component;
                }
            }
            $layer = [];
            foreach ($readyComponents as $component) {
                $layer = [...$layer, ...$components[$component]];
                unset($remaining[$component]);
            }
            sort($layer);
            $layers[] = $layer;
        }

        return $layers;
    }

    /** @param array<int|string, string[]> $graph @return array{array<int, string[]>, array<int|string, int>} */
    private function stronglyConnectedComponents(array $graph): array
    {
        $visited = [];
        $order = [];
        $visit = function (string $sku) use (&$visit, &$visited, &$order, $graph): void {
            if (isset($visited[$sku])) {
                return;
            }
            $visited[$sku] = true;
            foreach ($graph[$sku] as $dependency) {
                $visit($dependency);
            }
            $order[] = $sku;
        };
        foreach (array_keys($graph) as $sku) {
            // PHP converts integer-like SKU array keys to integers.
            $visit((string)$sku);
        }

        $reverse = array_fill_keys(array_keys($graph), []);
        foreach ($graph as $sku => $dependencies) {
            foreach ($dependencies as $dependency) {
                $reverse[$dependency][] = (string)$sku;
            }
        }
        $visited = [];
        $components = [];
        $componentBySku = [];
        $collect = function (
            string $sku,
            int $component
        ) use (
            &$collect,
            &$visited,
            &$components,
            &$componentBySku,
            $reverse
        ): void {
            if (isset($visited[$sku])) {
                return;
            }
            $visited[$sku] = true;
            $components[$component][] = $sku;
            $componentBySku[$sku] = $component;
            foreach ($reverse[$sku] as $dependent) {
                $collect($dependent, $component);
            }
        };
        foreach (array_reverse($order) as $sku) {
            if (isset($visited[$sku])) {
                continue;
            }
            $component = count($components);
            $components[$component] = [];
            $collect($sku, $component);
            sort($components[$component]);
        }

        return [$components, $componentBySku];
    }

    /** @return string[] */
    private function relatedSkus(ProductStateInterface $product): array
    {
        $skus = [
            ...$product->getRelations()->getVariantSkus(),
            ...array_map('strval', array_keys($product->getRelations()->getGroupedChildren())),
        ];
        foreach ($product->getValues() as $value) {
            if ($value->getType() !== 'product_relation') {
                continue;
            }
            foreach ($value->getTranslations() as $translation) {
                $skus = [...$skus, ...(is_array($translation) ? $translation : [$translation])];
            }
        }

        return array_values(array_unique($skus));
    }
}
