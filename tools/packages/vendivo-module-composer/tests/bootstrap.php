<?php

declare(strict_types=1);

$autoloadCandidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 4) . '/vendor/autoload.php',
];
foreach ($autoloadCandidates as $autoloadCandidate) {
    if (is_file($autoloadCandidate)) {
        require $autoloadCandidate;

        return;
    }
}

throw new RuntimeException('Unable to locate Composer autoload.php for package tests.');
