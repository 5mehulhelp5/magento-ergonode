'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {modulePath} = require('./module-paths.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const form = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/ui_component/category_form.xml'), 'utf8');
const di = fs.readFileSync(path.join(moduleRoot, 'etc/di.xml'), 'utf8');
const plugin = fs.readFileSync(path.join(moduleRoot, 'Model/CategoryFormDataProviderPlugin.php'), 'utf8');
const component = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/form/element/category-refresh.js'),
    'utf8'
);
const template = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/template/form/element/category-refresh.html'),
    'utf8'
);
const layout = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/layout/catalog_category_edit.xml'), 'utf8');
const controller = fs.readFileSync(
    path.join(
        modulePath('CategoryConsumerAdminUi', 'module-category-consumer-admin-ui'),
        'Controller/Adminhtml/Category/Refresh.php'
    ),
    'utf8'
);
const acl = fs.readFileSync(
    path.join(modulePath('CategoryConsumer', 'module-category-consumer'), 'etc/acl.xml'),
    'utf8'
);

test('category form contains a disabled non-EAV Ergonode code field', () => {
    const codeField = form.match(
        /<field name="ergonode_category_code"[\s\S]*?<\/field>/
    )?.[0] || '';

    assert.match(form, /<fieldset name="ergonode"/);
    assert.match(form, /<label translate="true">Ergonode<\/label>/);
    assert.match(form, /<field name="ergonode_category_code"[\s\S]*?<disabled>true<\/disabled>/);
    assert.match(form, /<label translate="true">Category Code<\/label>/);
    assert.doesNotMatch(form, />Ergonode Category Code</);
    assert.doesNotMatch(codeField, /attribute|eav/iu);
});

test('category data provider decorates form data from the consumer read contract', () => {
    assert.match(di, /Magento\\Catalog\\Model\\Category\\DataProvider/);
    assert.match(di, /CategoryFormDataProviderPlugin/);
    assert.match(plugin, /CategoryFormContextProviderInterface/);
    assert.match(plugin, /ergonode_category_code/);
    assert.match(plugin, /ergonode_category_tree_id/);
});

test('mapped category form offers a one-word refresh action backed by the consumer', () => {
    assert.match(form, /category-refresh/);
    assert.match(form, /ergonode\/category\/refresh/);
    assert.match(form, /data\.ergonode_category_code/);
    assert.match(template, /i18n: 'Refresh'/);
    assert.match(template, /refresh-ergonode-category-from-form/);
    assert.match(component, /canRefresh/);
    assert.match(component, /type: 'POST'/);
    assert.match(component, /category_id: Number\(this\.categoryId\(\) \|\| 0\)/);
    assert.match(component, /reloadPage/);
    assert.match(layout, /Ergonode_CategoryAttributeConsumerAdminUi::css\/category-form\.css/);
});

test('category refresh endpoint is POST-only, ACL protected and delegates to the base contract', () => {
    assert.match(controller, /implements HttpPostActionInterface/);
    assert.match(controller, /ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_sync'/);
    assert.match(controller, /CategoryEntityRefresherInterface/);
    assert.match(controller, /categoryRefresher->refresh\(\$categoryId\)/);
    assert.match(controller, /ResultFactory::TYPE_JSON/);
    assert.match(acl, /id="Ergonode_CategoryConsumer::category_tree_sync"/);
});
