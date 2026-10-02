<?php

declare(strict_types=1);

// Test server bound to loopback; it never accesses Ergonode or stores credentials.
$name = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!in_array($name, ['/first.png', '/replacement.png'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/png');
readfile(getenv('ERGONODE_MEDIA_TEST_FIXTURES') . $name);
