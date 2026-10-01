<?php
declare(strict_types=1);

// Domain fixture preparation for the dedicated E2E database only.
if (PHP_SAPI !== 'cli' || getcwd() !== '/var/www/e2e') {
    exit(1);
}
require getcwd() . '/app/bootstrap.php';
$profile = json_decode(file_get_contents(BP . '/profile.json'), true, flags: JSON_THROW_ON_ERROR);
$environment = require BP . '/app/etc/env.php';
if (($environment['e2e']['id'] ?? '') !== $profile['id']
    || $environment['db']['connection']['default']['dbname'] !== 'vendivo_e2e') {
    throw new RuntimeException('Language fixtures require the isolated E2E profile.');
}
$om = Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager();
$resource = $om->get(Magento\Framework\App\ResourceConnection::class);
$db = $resource->getConnection();
if ($db->fetchOne('SELECT DATABASE()') !== 'vendivo_e2e') {
    throw new RuntimeException('Unexpected database.');
}
$source = $db->fetchRow("SELECT * FROM admin_user WHERE username = 'e2e_admin'");
if (!$source) {
    throw new RuntimeException('The isolated installer account is required.');
}
$db->beginTransaction();
try {
    $default = $db->fetchRow("SELECT * FROM store WHERE code = 'default'");
    if (!$default) {
        throw new RuntimeException('The isolated default Store View is required.');
    }
    if (!$db->fetchOne("SELECT store_id FROM store WHERE code = 'base_pl'")) {
        $db->insert('store', ['code' => 'base_pl', 'website_id' => $default['website_id'],
            'group_id' => $default['group_id'], 'name' => 'Polski', 'sort_order' => 2, 'is_active' => 1]);
    }
    $storeId = (int)$db->fetchOne("SELECT store_id FROM store WHERE code = 'base_pl'");
    $db->insertOnDuplicate('core_config_data', ['scope' => 'stores', 'scope_id' => $storeId,
        'path' => 'general/locale/code', 'value' => 'pl_PL'], ['value']);
    foreach (['en_GB', 'pl_PL'] as $code) {
        $db->insertOnDuplicate('ergonode_language', ['language_code' => $code], ['language_code']);
    }
    $values = ['e2e/environment/id' => $profile['id'],
        'ergonode_connection/general/enabled' => '1', 'ergonode_connection/general/environment' => 'test',
        'ergonode_connection/general/mode' => 'read',
        'ergonode_connection/test/url' => 'http://playwright:18092/api/graphql/',
        'ergonode_connection/test/consumer/api_key' => $om
            ->get(Magento\Framework\Encryption\EncryptorInterface::class)
            ->encrypt('language-e2e-fixture')];
    foreach ($values as $path => $value) {
        $db->insertOnDuplicate('core_config_data', ['scope' => 'default', 'scope_id' => 0,
            'path' => $path, 'value' => $value], ['value']);
    }
    $mapping = 'Ergonode_Language::language_mapping';
    $resources = ['Magento_Backend::all', 'Magento_Backend::admin', 'Magento_Backend::dashboard',
        'Ergonode_Core::main', $mapping, $mapping . '_save', $mapping . '_refresh'];
    foreach (['full', 'viewer', 'editor', 'denied'] as $role) {
        $name = 'pw_lang_e2e_' . $role;
        if ($db->fetchOne('SELECT user_id FROM admin_user WHERE username = ?', [$name])) {
            continue;
        }
        $db->insert('admin_user', ['username' => $name, 'password' => $source['password'],
            'firstname' => 'Language', 'lastname' => 'E2E', 'email' => $name . '@example.invalid',
            'is_active' => 1, 'interface_locale' => 'en_US']);
        $userId = (int)$db->lastInsertId('admin_user');
        $db->insert('authorization_role', [
            'role_name' => $name, 'role_type' => 'G', 'tree_level' => 1, 'user_type' => '2',
        ]);
        $groupId = (int)$db->lastInsertId('authorization_role');
        $db->insert('authorization_role', ['role_name' => $name, 'role_type' => 'U', 'parent_id' => $groupId,
            'tree_level' => 2, 'user_type' => '2', 'user_id' => $userId]);
        foreach ($resources as $aclResource) {
            $allowed = $aclResource !== 'Magento_Backend::all'
                && !($role === 'denied' && str_starts_with($aclResource, $mapping))
                && !($role === 'viewer' && in_array($aclResource, [$mapping . '_save', $mapping . '_refresh'], true))
                && !($role === 'editor' && $aclResource === $mapping . '_refresh');
            $db->insert('authorization_rule', ['role_id' => $groupId, 'resource_id' => $aclResource,
                'permission' => $allowed ? 'allow' : 'deny', 'privileges' => '']);
        }
    }
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
$om->get(Magento\Framework\App\Cache\Type\Config::class)->clean();
echo "Isolated Language fixtures ready.\n";
