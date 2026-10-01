<?php

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\Encryption\EncryptorInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
// Local, temporary execution harness. Never emit configuration values.
require getcwd() . '/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$manager = $bootstrap->getObjectManager();
$resource = $manager->get(ResourceConnection::class);
$db = $resource->getConnection();
$table = $resource->getTableName('core_config_data');
$paths = array_map(static fn($suffix) => 'ergonode_connection/' . $suffix, [
    'general/enabled', 'general/environment', 'general/mode', 'test/url', 'test/consumer/api_key',
]);
$read = static fn() => $db->fetchAll(
    $db->select()->from($table)->where('scope = ?', 'default')->where('scope_id = ?', 0)
        ->where('path IN (?)', $paths)->order('path')
);
$cache = $manager->get(Config::class);
$backup = BP . '/var/test-state/language-e2e-connection.json';
$original = null;
$userIds = [];
$roleIds = [];
$exit = 1;
$keyServer = null;
if ((int)$db->fetchOne('SELECT GET_LOCK(?, 0)', ['ergonode-connection-playwright']) !== 1) {
    throw new RuntimeException('Another test owns the connection configuration lock.');
}
try {
    if (is_file($backup) || is_file(BP . '/var/test-state/ergonode-connection.json')) {
        throw new RuntimeException('An existing connection recovery snapshot needs inspection first.');
    }
    $config = Yaml::parseFile(BP . '/app/etc/playwright.yaml');
    $host = parse_url($config['magento']['baseUrl'] ?? '', PHP_URL_HOST);
    if (!is_string($host)
        || (!str_ends_with($host, '.ddev.site') && !in_array($host, ['localhost', '127.0.0.1'], true))
    ) {
        throw new RuntimeException('Local Magento is required.');
    }
    $accountAlias = getenv('MAGENTO_TEST_ACCOUNT') ?: ($config['magento']['defaultAccount'] ?? '');
    $username = $config['magento']['accounts'][$accountAlias]['username'] ?? '';
    $userTable = $resource->getTableName('admin_user');
    $roleTable = $resource->getTableName('authorization_role');
    $ruleTable = $resource->getTableName('authorization_rule');
    $sourceUser = $db->fetchRow($db->select()->from($userTable)->where('username = ?', $username));
    if (!$sourceUser || (int)$sourceUser['is_active'] !== 1) {
        throw new RuntimeException('The selected local test account must already exist and be active.');
    }
    $testRoles = ['full', 'viewer', 'editor', 'denied'];
    $testNames = array_map(static fn($role) => 'pw_lang_e2e_' . $role, $testRoles);
    $occupied = $db->fetchOne($db->select()->from($userTable, ['COUNT(*)'])->where('username IN (?)', $testNames));
    $occupiedRoles = $db->fetchOne(
        $db->select()->from($roleTable, ['COUNT(*)'])->where('role_name IN (?)', $testNames)
    );
    if ($occupied || $occupiedRoles) {
        throw new RuntimeException('Reserved accounts or roles already exist; inspect before recovery.');
    }
    $url = 'http://playwright:18089/api/graphql/';
    $key = 'language-e2e-fixture'; // Synthetic local fixture key, never a remote credential.
    $rows = $read();
    if (!is_dir(dirname($backup))) {
        mkdir(dirname($backup), 0700, true);
    }
    $oldMask = umask(0077);
    $file = fopen($backup, 'x');
    umask($oldMask);
    if ($file === false) {
        throw new RuntimeException('Cannot create the protected recovery snapshot.');
    }
    fwrite($file, json_encode(['table' => $table, 'paths' => $paths, 'rows' => $rows], JSON_THROW_ON_ERROR));
    fclose($file);
    $original = $rows;
    $values = [
        'general/enabled' => '1', 'general/environment' => 'test', 'general/mode' => 'read',
        'test/url' => $url,
        'test/consumer/api_key' => $manager->get(EncryptorInterface::class)->encrypt($key),
    ];
    $db->beginTransaction();
    try {
        foreach ($values as $suffix => $value) {
            $db->insertOnDuplicate($table, [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'ergonode_connection/' . $suffix,
                'value' => $value,
            ], ['value']);
        }
        $mappingResource = 'Ergonode_Language::language_mapping';
        $aclResources = [
            'Magento_Backend::all', 'Magento_Backend::admin', 'Magento_Backend::dashboard',
            'Ergonode_Core::main', $mappingResource, $mappingResource . '_save', $mappingResource . '_refresh',
        ];
        foreach ($testRoles as $role) {
            $testName = 'pw_lang_e2e_' . $role;
            $db->insert($userTable, [
                'username' => $testName, 'password' => $sourceUser['password'],
                'firstname' => 'Language', 'lastname' => 'E2E', 'email' => $testName . '@example.invalid',
                'is_active' => 1, 'interface_locale' => 'en_US',
            ]);
            $userId = (int)$db->lastInsertId($userTable);
            $userIds[] = $userId;
            $db->insert($roleTable, [
                'role_name' => $testName, 'role_type' => 'G', 'tree_level' => 1, 'user_type' => '2',
            ]);
            $groupId = (int)$db->lastInsertId($roleTable);
            $roleIds[] = $groupId;
            $db->insert($roleTable, [
                'role_name' => $testName, 'role_type' => 'U', 'parent_id' => $groupId,
                'tree_level' => 2, 'user_type' => '2', 'user_id' => $userId,
            ]);
            $roleIds[] = (int)$db->lastInsertId($roleTable);
            foreach ($aclResources as $aclResource) {
                $allowed = $aclResource !== 'Magento_Backend::all'
                    && !($role === 'denied' && str_starts_with($aclResource, $mappingResource))
                    && !($role === 'viewer'
                        && in_array($aclResource, [$mappingResource . '_save', $mappingResource . '_refresh'], true))
                    && !($role === 'editor' && $aclResource === $mappingResource . '_refresh');
                $db->insert($ruleTable, [
                    'role_id' => $groupId, 'resource_id' => $aclResource,
                    'permission' => $allowed ? 'allow' : 'deny', 'privileges' => '',
                ]);
            }
        }
        file_put_contents($backup, json_encode([
            'table' => $table, 'paths' => $paths, 'rows' => $rows, 'userIds' => $userIds, 'roleIds' => $roleIds,
        ], JSON_THROW_ON_ERROR));
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    unset($config, $url, $key, $values, $sourceUser);
    $cache->clean();
    $keyServer = new Process([PHP_BINARY, '-S', '0.0.0.0:18090', __DIR__ . '/admin-key.php']);
    $keyServer->setTimeout(null);
    $keyServer->start();
    echo "Temporary test/read connection applied; recovery snapshot protected.\n";
    $ids = explode(',', $argv[1] ?? 'ERG-LANG-007,ERG-LANG-008,ERG-LANG-009,ERG-LANG-010,ERG-LANG-011,ERG-LANG-012');
    $exit = 0;
    foreach ($ids as $id) {
        if (!preg_match('/^ERG-LANG-0(?:0[7-9]|1[0-2])$/D', $id)) {
            throw new RuntimeException('Invalid scoped test ID.');
        }
        $process = new Process([
            'make', '-f', '.agents/backend/Makefile', 'playwright-run', 'RUNTIME=env', 'test=' . $id,
        ]);
        $process->setTimeout(330);
        $result = $process->run(static function (string $type, string $buffer): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
        });
        if ($result !== 0) {
            $exit = $result;
            break;
        }
    }
} catch (Throwable $error) {
    // Configuration/database exceptions can carry secrets; report only a safe type.
    fwrite(STDERR, 'Harness stopped: ' . get_class($error) . ". No configuration values were emitted.\n");
    $exit = 1;
} finally {
    $keyServer?->stop();
    if ($original !== null) {
        if ($roleIds !== []) {
            $db->delete($roleTable, ['role_id IN (?)' => $roleIds]);
        }
        if ($userIds !== []) {
            $db->delete($userTable, ['user_id IN (?)' => $userIds]);
        }
        $db->beginTransaction();
        try {
            $db->delete($table, ['scope = ?' => 'default', 'scope_id = ?' => 0, 'path IN (?)' => $paths]);
            foreach ($original as $row) {
                $db->insert($table, $row);
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw new RuntimeException('Connection restoration failed; protected recovery snapshot retained.');
        }
        $cache->clean();
        if ($read() !== $original) {
            throw new RuntimeException('Restored configuration differs; recovery snapshot retained.');
        }
        if ($userIds !== [] && $db->fetchOne(
            $db->select()->from($userTable, ['COUNT(*)'])->where('user_id IN (?)', $userIds)
        )) {
            throw new RuntimeException('Temporary user cleanup failed; recovery snapshot retained.');
        }
        if ($roleIds !== [] && $db->fetchOne(
            $db->select()->from($roleTable, ['COUNT(*)'])->where('role_id IN (?)', $roleIds)
        )) {
            throw new RuntimeException('Temporary role cleanup failed; recovery snapshot retained.');
        }
        unlink($backup);
        echo "Temporary accounts and roles removed and verified.\n";
        echo "Original five configuration paths restored exactly; config cache refreshed; recovery snapshot removed.\n";
    }
    $db->fetchOne('SELECT RELEASE_LOCK(?)', ['ergonode-connection-playwright']);
}
exit($exit);
