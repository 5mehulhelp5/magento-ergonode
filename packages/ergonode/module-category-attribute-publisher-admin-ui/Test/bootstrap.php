<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 4);
require_once $backendRoot . '/dev/tests/unit/framework/bootstrap.php';
$loader = require $backendRoot . '/vendor/autoload.php';
$loader->addPsr4('Ergonode\\CategoryAttributePublisherAdminUi\\', dirname(__DIR__));
$loader->addPsr4(
    'Ergonode\\CategoryAttributePublisher\\',
    $backendRoot . '/vendor/ergonode/module-category-attribute-publisher'
);
