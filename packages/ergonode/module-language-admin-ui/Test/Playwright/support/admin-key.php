<?php

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

// Ephemeral PHP development-server router, never a Magento controller or public route.
if (PHP_SAPI !== 'cli-server'
    || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || ($_SERVER['REQUEST_URI'] ?? '') !== '/language-index-key'
    || ($_SERVER['HTTP_X_LANGUAGE_FIXTURE_KEY'] ?? '') !== 'language-e2e-fixture'
) {
    http_response_code(404);
    exit;
}
$input = json_decode((string)file_get_contents('php://input'), true);
$formKey = is_array($input) ? ($input['formKey'] ?? null) : null;
if (!is_string($formKey) || !preg_match('/^[a-zA-Z0-9]{16}$/D', $formKey)) {
    http_response_code(400);
    exit;
}
require getcwd() . '/app/bootstrap.php';
$manager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$config = $manager->get(ScopeConfigInterface::class);
$environment = require BP . '/app/etc/env.php';
$expectedUrl = isset($environment['e2e']['id'])
    ? 'http://playwright:18092/api/graphql/' : 'http://playwright:18089/api/graphql/';
if ($config->getValue('ergonode_connection/test/url') !== $expectedUrl
    || $config->getValue('ergonode_connection/general/environment') !== 'test'
) {
    http_response_code(409);
    exit;
}
// Use Magento's current hash/HMAC contract without exporting its encryption key.
$key = $manager->get(EncryptorInterface::class)->getHash('ergonodelanguageindex' . $formKey);
header('Content-Type: application/json');
echo json_encode(['key' => $key], JSON_THROW_ON_ERROR);
