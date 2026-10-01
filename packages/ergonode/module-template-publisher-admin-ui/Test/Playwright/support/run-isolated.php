<?php

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$magentoRoot = getcwd();
require $magentoRoot . '/app/bootstrap.php';
$bootstrap = Bootstrap::create($magentoRoot, $_SERVER);
$manager = $bootstrap->getObjectManager();
$resource = $manager->get(ResourceConnection::class);
$database = $resource->getConnection();
$cache = $manager->get(Config::class);
$userTable = $resource->getTableName('admin_user');
$roleTable = $resource->getTableName('authorization_role');
$ruleTable = $resource->getTableName('authorization_rule');
$username = 'pw_template_publisher_acl';
$backup = $magentoRoot . '/var/test-state/template-publisher-permissions.json';
$userId = null;
$roleIds = [];
$groupId = null;
$backupCreated = false;
$exit = 1;

if ((int)$database->fetchOne('SELECT GET_LOCK(?, 0)', ['ergonode-template-publisher-playwright']) !== 1) {
    throw new RuntimeException('Another template publisher permission test is running.');
}

try {
    if (is_file($backup)) {
        throw new RuntimeException('A template publisher permission recovery snapshot needs inspection first.');
    }
    $config = Yaml::parseFile($magentoRoot . '/app/etc/playwright.yaml');
    $host = parse_url($config['magento']['baseUrl'] ?? '', PHP_URL_HOST);
    if (!is_string($host)
        || (!str_ends_with($host, '.ddev.site') && !in_array($host, ['localhost', '127.0.0.1'], true))
    ) {
        throw new RuntimeException('Local Magento is required.');
    }
    $accountAlias = getenv('MAGENTO_TEST_ACCOUNT') ?: ($config['magento']['defaultAccount'] ?? '');
    $sourceUsername = $config['magento']['accounts'][$accountAlias]['username'] ?? '';
    $sourceUser = $database->fetchRow(
        $database->select()->from($userTable)->where('username = ?', $sourceUsername)
    );
    if (!$sourceUser || (int)$sourceUser['is_active'] !== 1) {
        throw new RuntimeException('The selected local test account must already exist and be active.');
    }
    $occupied = (int)$database->fetchOne(
        $database->select()->from($userTable, ['COUNT(*)'])->where('username = ?', $username)
    );
    $occupiedRoles = (int)$database->fetchOne(
        $database->select()->from($roleTable, ['COUNT(*)'])->where('role_name = ?', $username)
    );
    if ($occupied || $occupiedRoles) {
        throw new RuntimeException('Reserved account or role already exists; inspect it before recovery.');
    }
    if (!is_dir(dirname($backup))) {
        mkdir(dirname($backup), 0700, true);
    }
    $oldMask = umask(0077);
    $file = fopen($backup, 'x');
    umask($oldMask);
    if ($file === false) {
        throw new RuntimeException('Cannot create the protected recovery snapshot.');
    }
    fwrite($file, json_encode(['username' => $username], JSON_THROW_ON_ERROR));
    fclose($file);
    $backupCreated = true;

    $database->beginTransaction();
    try {
        $database->insert($userTable, [
            'username' => $username,
            'password' => $sourceUser['password'],
            'firstname' => 'Template',
            'lastname' => 'Publisher E2E',
            'email' => $username . '@example.invalid',
            'is_active' => 1,
            'interface_locale' => 'en_US',
        ]);
        $userId = (int)$database->lastInsertId($userTable);
        $database->insert($roleTable, [
            'role_name' => $username,
            'role_type' => 'G',
            'tree_level' => 1,
            'user_type' => '2',
        ]);
        $groupId = (int)$database->lastInsertId($roleTable);
        $roleIds[] = $groupId;
        $database->insert($roleTable, [
            'role_name' => $username,
            'role_type' => 'U',
            'parent_id' => $groupId,
            'tree_level' => 2,
            'user_type' => '2',
            'user_id' => $userId,
        ]);
        $roleIds[] = (int)$database->lastInsertId($roleTable);
        foreach ([
            'Magento_Backend::admin' => 'allow',
            'Magento_Backend::dashboard' => 'allow',
            'Ergonode_Core::main' => 'allow',
            'Ergonode_TemplateConsumer::template_mapping' => 'allow',
            'Ergonode_TemplateConsumer::template_save' => 'deny',
        ] as $aclResource => $permission) {
            $database->insert($ruleTable, [
                'role_id' => $groupId,
                'resource_id' => $aclResource,
                'permission' => $permission,
                'privileges' => '',
            ]);
        }
        file_put_contents($backup, json_encode([
            'username' => $username,
            'userId' => $userId,
            'roleIds' => $roleIds,
            'groupId' => $groupId,
        ], JSON_THROW_ON_ERROR));
        $database->commit();
    } catch (Throwable $error) {
        $database->rollBack();
        throw $error;
    }

    unset($config, $sourceUser);
    $cache->clean();
    echo "Temporary mapping-only account and role applied; config cache refreshed.\n";
    $process = new Process([
        'make', '-f', '.agents/backend/Makefile', 'playwright-run',
        'RUNTIME=env', 'test=ERG-TPL-PUB-001',
    ]);
    $process->setTimeout(180);
    $exit = $process->run(static function (string $type, string $buffer): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    });
} catch (Throwable $error) {
    fwrite(STDERR, 'Harness stopped: ' . get_class($error) . ". No configuration values were emitted.\n");
    $exit = 1;
} finally {
    if ($groupId !== null) {
        $database->delete($ruleTable, ['role_id = ?' => $groupId]);
    }
    if ($roleIds !== []) {
        $database->delete($roleTable, ['role_id IN (?)' => $roleIds]);
    }
    if ($userId !== null) {
        $database->delete($userTable, ['user_id = ?' => $userId]);
    }
    $cache->clean();
    $remainingUsers = (int)$database->fetchOne(
        $database->select()->from($userTable, ['COUNT(*)'])->where('username = ?', $username)
    );
    $remainingRoles = (int)$database->fetchOne(
        $database->select()->from($roleTable, ['COUNT(*)'])->where('role_name = ?', $username)
    );
    $cleanupFailed = $remainingUsers || $remainingRoles;
    if (!$cleanupFailed && $backupCreated && is_file($backup)) {
        unlink($backup);
    }
    $database->fetchOne('SELECT RELEASE_LOCK(?)', ['ergonode-template-publisher-playwright']);
    if ($cleanupFailed) {
        throw new RuntimeException('Temporary account or role cleanup failed; recovery snapshot retained.');
    }
    echo "Temporary account and role removed and verified; config cache refreshed.\n";
}

exit($exit);
