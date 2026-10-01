'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const backendRoot = path.resolve(moduleRoot, '../../..');
const read = (file) => fs.readFileSync(path.join(moduleRoot, file), 'utf8');

test('category option mapping owns its layout assets and uses the shared draft flow', () => {
    const layout = read('view/adminhtml/layout/ergonode_category_option_index.xml');
    const block = read('Block/Adminhtml/CategoryOption/Mapping.php');
    const optionTemplate = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/templates/option/mapping.phtml'
    ), 'utf8');
    const optionScript = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/option-mapping.js'
    ), 'utf8');
    const navigationProvider = fs.readFileSync(path.join(backendRoot, 'vendor/ergonode/module-category-attribute-consumer-admin-ui/Model/Navigation/CategoryAttributeNavigationItemProvider.php'), 'utf8');
    const modalController = read('Controller/Adminhtml/Category/Option/Modal.php');

    assert.match(layout, /Ergonode_CoreAdminUi::css\/ergonode-workspace\.css/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/attribute-mapping\.css/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/option-mapping\.css/);
    assert.doesNotMatch(optionTemplate, /<link rel="stylesheet"|workspaceStylesheetUrl|stylesheetUrl/);
    assert.match(optionTemplate, /\$block->canSynchronizeOptions\(\)/);
    assert.match(block, /canSynchronizeOptions\(\): bool\s*\{\s*return false;/);
    assert.doesNotMatch(block, /create_magento_option|getCreateMagentoOptionUrl|hasAttributeContexts/);
    assert.match(optionScript, /function buildPendingMagentoOption\(leftPayload\)/);
    assert.match(optionScript, /pending_create: true/);
    assert.equal(fs.existsSync(path.join(
        moduleRoot,
        'Controller/Adminhtml/Category/Option/CreateMagento.php'
    )), false);
    assert.equal(fs.existsSync(path.join(moduleRoot, 'Model/PendingCategoryOptionFactory.php')), false);
    assert.match(block, /return 'category_options'/);
    assert.match(block, /getData\('embedded'\)/);
    assert.match(modalController, /implements HttpGetActionInterface/);
    assert.match(modalController, /public const string ADMIN_RESOURCE/);
    assert.match(modalController, /Ergonode_CategoryConsumer::category_attribute_mapping/);
    assert.doesNotMatch(navigationProvider, /'label' => __\('Options'\)/);
    assert.doesNotMatch(navigationProvider, /ergonode\/category_option\/index/);
});
