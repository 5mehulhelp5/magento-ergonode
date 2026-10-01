<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 4);
require_once $backendRoot . '/dev/tests/unit/framework/bootstrap.php';
$loader = require $backendRoot . '/vendor/autoload.php';
$loader->addPsr4(
    'Ergonode\\ProductCategoryAttribute\\',
    $backendRoot . '/packages/ergonode/module-product-category-attribute'
);
$loader->addPsr4('Ergonode\\ProductCategoryAttributePublisher\\', dirname(__DIR__));
