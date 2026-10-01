'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {moduleRoot} = require('./project-paths.cjs');
const categoryRoot = moduleRoot('Category', 'module-category');
const categoryAdminUiRoot = moduleRoot('CategoryAdminUi', 'module-category-admin-ui');

function read(relativePath) {
    const neutral = /^(?:Controller\/Adminhtml\/Category\/Tree\/(?:Save|Delete|Reorder|RefreshTreeOptions|Mapping\/(?:Save|Load|Refresh|DeleteSnapshot|Edit|Validate|AutoMap))\.php|Block\/Adminhtml\/CategoryTreeMapping\/Index\.php|view\/adminhtml\/web\/js\/category-tree-mapping\.js)$/;
    if (relativePath.startsWith('../../../Category/etc/')) {
        return fs.readFileSync(path.join(categoryRoot, relativePath.slice('../../../Category/'.length)), 'utf8');
    }
    if (relativePath.startsWith('../../../CategoryConsumer/etc/')) {
        return fs.readFileSync(path.join(moduleRoot('CategoryConsumer', 'module-category-consumer'), relativePath.slice('../../../CategoryConsumer/'.length)), 'utf8');
    }
    if (relativePath.startsWith('../../../')) { return fs.readFileSync(path.resolve(__dirname, relativePath), 'utf8'); }
    const relative = relativePath.replace(/^\.\.\/\.\.\//, '');
    const root = neutral.test(relative) ? categoryAdminUiRoot : path.resolve(__dirname, '../..');
    return fs.readFileSync(path.join(root, relative), 'utf8');
}

test('Category Tree mutations are POST-only and protected by dedicated ACL resources', () => {
    const contracts = {
        '../../Controller/Adminhtml/Category/Tree/Save.php': 'category_tree_save',
        '../../Controller/Adminhtml/Category/Tree/Delete.php': 'category_tree_save',
        '../../Controller/Adminhtml/Category/Tree/Reorder.php': 'category_tree_save',
        '../../Controller/Adminhtml/Category/Tree/RefreshTreeOptions.php': 'category_tree_manage',
        '../../Controller/Adminhtml/Category/Tree/Sync.php': 'category_tree_sync',
        '../../Controller/Adminhtml/Category/Tree/ResetSyncCursor.php': 'category_tree_sync'
    };
    const acl = read('../../../CategoryConsumer/etc/acl.xml') + read('../../../Category/etc/acl.xml');

    for (const [controller, resource] of Object.entries(contracts)) {
        const source = read(controller);
        assert.match(source, /implements HttpPostActionInterface/);
        assert.match(source, new RegExp(`ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::${resource}'`));
        assert.match(acl, new RegExp(`id="Ergonode_CategoryConsumer::${resource}"`));
    }
});

test('sync cursor reset is JSON-only and delegates through the Category Consumer reset contract', () => {
    const source = read('../../Controller/Adminhtml/Category/Tree/ResetSyncCursor.php');

    assert.match(source, /ResultFactory::TYPE_JSON/);
    assert.match(source, /CategorySynchronizationActionInterface/);
    assert.match(source, /cursorResetter->execute\(/);
    assert.doesNotMatch(source, /CategoryTreeSyncMetadataProviderInterface|sync_metadata|synced_at/);
    assert.doesNotMatch(source, /category_tree_id/);
    assert.match(source, /catch \(Exception \$exception\)/);
    assert.doesNotMatch(source, /ObjectManager/);
});

test('Category Tree save returns to the unified mapping page and hides unexpected technical errors', () => {
    const source = read('../../Controller/Adminhtml/Category/Tree/Save.php');

    assert.match(source, /getPostValue\(\)/);
    assert.match(source, /catch \(LocalizedException \$exception\)/);
    assert.match(source, /catch \(Exception \$exception\)/);
    assert.match(source, /addExceptionMessage\(/);
    assert.match(source, /'ergonode\/category_tree_mapping\/edit'/);
    assert.doesNotMatch(source, /DataPersistor|DataProvider|category_tree\/edit/);
    assert.doesNotMatch(source, /catch \(Throwable \$exception\)/);
    assert.doesNotMatch(
        source,
        /catch \(Throwable \$exception\)[\s\S]*?addErrorMessage\(\$exception->getMessage\(\)\)/
    );
});

test('redirecting Category Tree operations show exceptions through generic messages', () => {
    const controllers = ['Delete.php'];

    for (const controller of controllers) {
        const source = read(`../../Controller/Adminhtml/Category/Tree/${controller}`);
        assert.match(source, /catch \(LocalizedException \$exception\)/);
        assert.match(source, /catch \(Exception \$exception\)/);
        assert.match(source, /addExceptionMessage\(/);
        assert.doesNotMatch(source, /catch \(Throwable \$exception\)/);
    }
});

test('tree option refresh returns JSON and does not expose unexpected errors', () => {
    const source = read('../../Controller/Adminhtml/Category/Tree/RefreshTreeOptions.php');

    assert.match(source, /ResultFactory::TYPE_JSON/);
    assert.match(source, /catch \(LocalizedException \$exception\)/);
    assert.match(source, /catch \(Exception \$exception\)/);
    assert.match(source, /Could not load category trees from Ergonode\./);
    assert.doesNotMatch(source, /__\([^\n]*\$exception->getMessage\(\)/);
});

test('refresh, auto-map and stream synchronization have separate backend contracts', () => {
    const mappingRefresh = read('../../Controller/Adminhtml/Category/Tree/Mapping/Refresh.php');
    const mappingAutoMap = read('../../Controller/Adminhtml/Category/Tree/Mapping/AutoMap.php');
    const streamSync = read('../../Controller/Adminhtml/Category/Tree/Sync.php');
    const mappingUi = read('../../view/adminhtml/web/js/category-tree-mapping.js');
    const consumerUi = read('../../view/adminhtml/web/js/category-mapping-consumer.js');
    const syncUi = read('../../view/adminhtml/web/js/category-sync-run.js');
    const syncResponse = read('../../Model/CategorySynchronizationResponse.php');

    assert.match(mappingRefresh, /CategoryTreeRefreshServiceInterface/);
    assert.match(mappingRefresh, /refreshService->refresh\(/);
    assert.doesNotMatch(mappingRefresh, /CategoryReconciliationServiceInterface|MODE_PREVIEW|MODE_APPLY/);
    assert.doesNotMatch(mappingRefresh, /'sync_metadata'\s*=>|'refresh'\s*=>/);
    assert.match(mappingAutoMap, /CategoryAutoMapperInterface/);
    assert.match(mappingAutoMap, /autoMapper->suggest\(/);
    assert.doesNotMatch(mappingAutoMap, /CategoryReconciliationServiceInterface|MODE_PREVIEW/);
    assert.match(streamSync, /CategorySynchronizationRunInterface/);
    assert.match(streamSync, /synchronizationRun->execute\(/);
    assert.match(streamSync, /synchronization_scope/);
    assert.match(streamSync, /synchronization_action/);
    assert.match(streamSync, /response->format\(\$state\)/);
    assert.match(syncResponse, /CategoryTreeMappingUiProvider/);
    assert.match(syncResponse, /\$state\['completed'\] = in_array\(\$state\['state'\], \['success', 'warning'\], true\)/);
    assert.doesNotMatch(streamSync, /MODE_PREVIEW|MODE_APPLY/);
    assert.match(mappingUi, /post\(config\.urls\.auto_map/);
    assert.doesNotMatch(consumerUi, /post\(config\.urls\.auto_map/);
    assert.match(mappingUi, /post\(config\.urls\.refresh, \{category_tree_id: categoryTreeId\}\)/);
    assert.match(consumerUi, /syncRun\.start\(synchronizationScope, resetCursor, categoryTreeId\)/);
    assert.match(syncUi, /data\.synchronization_action = force \? 'reset-cursor-and-sync' : 'sync'/);
    assert.match(syncUi, /options\.request\(options\.urls\.sync, data, 0\)/);
    assert.match(consumerUi, /\[data-role="sync-category-trees"\]/);
    assert.doesNotMatch(mappingUi, /function refreshCategories\(cursor|cursor:\s*cursor/);
    assert.doesNotMatch(mappingUi, /category-auto-matcher|autoMatcher\.match/);
});

test('legacy Category Tree pages and manual Magento creation stay removed', () => {
    const removedPaths = [
        '../../Controller/Adminhtml/Category/Tree/Index.php',
        '../../Controller/Adminhtml/Category/Tree/Edit.php',
        '../../Controller/Adminhtml/Category/Tree/Refresh.php',
        '../../Controller/Adminhtml/Category/Tree/RefreshTrees.php',
        '../../Controller/Adminhtml/Category/Tree/Mapping/CreateMagento.php',
        '../../view/adminhtml/layout/ergonode_category_tree_index.xml',
        '../../view/adminhtml/layout/ergonode_category_tree_edit.xml',
        '../../view/adminhtml/ui_component/ergonode_category_tree_listing.xml',
        '../../view/adminhtml/ui_component/ergonode_category_tree_form.xml'
    ];
    const block = read('../../Block/Adminhtml/CategoryTreeMapping/Index.php');
    const acl = read('../../../CategoryConsumer/etc/acl.xml') + read('../../../Category/etc/acl.xml');
    const di = read('../../../CategoryConsumer/etc/di.xml');

    for (const removedPath of removedPaths) {
        assert.equal(fs.existsSync(path.resolve(__dirname, removedPath)), false);
    }
    assert.doesNotMatch(block, /create_magento|createMagento/);
    assert.doesNotMatch(acl, /category_tree_refresh/);
    assert.doesNotMatch(di, /ManualCategoryCreator/);
});
