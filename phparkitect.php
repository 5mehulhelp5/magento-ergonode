<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\IsInterface;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

require_once __DIR__ . '/tools/quality/modules.php';

return static function (Config $config): void {
    $config->skipParsingCustomAnnotations();
    $modules = qualityModules();
    $base = $areas = $models = $presentation = $apis = $apiExceptions = [];
    foreach ($modules as $name => $path) {
        $namespace = str_replace('_', '\\', $name);
        if (preg_match('/(AdminUi|Adminhtml|GraphQl|WebApi|FrontendUi|Frontend|SampleData)$/', $name)) {
            $areas[] = $namespace;
        } else {
            $base[] = $namespace;
        }
        $models[] = $namespace . '\\Model';
        $apis[] = $namespace . '\\Api';
        $apiExceptions[] = $namespace . '\\Api\\Exception';
        foreach (['Controller', 'Block', 'Ui'] as $layer) {
            $presentation[] = $namespace . '\\' . $layer;
        }
    }
    $paths = array_filter(explode(',', (string)getenv('ARKITECT_PATHS'))) ?: array_values($modules);
    $classSet = ClassSet::fromDir(...array_map(
        static fn (string $path): string => __DIR__ . '/' . $path,
        $paths
    ));
    $classSet->excludePath('**/Test/**');
    $classSet->excludePath('Test/**');
    $rules = [
        Rule::allClasses()->that(new ResideInOneOfTheseNamespaces(...$models))
            ->should(new NotDependsOnTheseNamespaces($presentation))
            ->because('Models must not depend on controllers, blocks or UI.'),
        Rule::allClasses()->except(...$apiExceptions)
            ->that(new ResideInOneOfTheseNamespaces(...$apis))->should(new IsInterface())
            ->because('Public API contracts are interfaces; API exceptions are allowed.'),
    ];
    if ($base !== [] && $areas !== []) {
        $rules[] = Rule::allClasses()->that(new ResideInOneOfTheseNamespaces(...$base))
            ->should(new NotDependsOnTheseNamespaces($areas))
            ->because('Base modules must be usable without presentation and area modules.');
    }
    $config->add($classSet, ...$rules);
};
