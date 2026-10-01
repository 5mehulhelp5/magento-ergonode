<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__, 4);
$loader = require $backendRoot . '/vendor/autoload.php';
$loader->addPsr4('Ergonode\\ProductCategoryAttribute\\', dirname(__DIR__));
require $backendRoot . '/dev/tests/integration/framework/bootstrap.php';
