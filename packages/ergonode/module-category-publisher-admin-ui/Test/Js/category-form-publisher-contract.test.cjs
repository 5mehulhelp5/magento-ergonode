'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const form = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/ui_component/category_form.xml'), 'utf8');
const component = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/form/element/category-create.js'),
    'utf8'
);
const jsonPost = fs.readFileSync(path.join(moduleRoot, '../module-publisher-admin-ui/view/adminhtml/web/js/json-post.js'), 'utf8');
const manualAuth = fs.readFileSync(path.join(moduleRoot, '../module-publisher-admin-ui/view/adminhtml/web/js/manual-auth.js'), 'utf8');
const template = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/template/form/element/category-create.html'),
    'utf8'
);
const controller = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Category/Create.php'),
    'utf8'
);

test('publisher composes its action into the consumer-owned Ergonode fieldset', () => {
    assert.match(form, /<fieldset name="ergonode">/);
    assert.match(form, /category-create/);
    assert.match(form, /ergonode_manual\/category\/create/);
    assert.match(form, /data\.ergonode_category_code/);
    assert.match(form, /data\.ergonode_category_tree_id/);
    assert.match(template, /i18n: 'Publish'/);
    assert.match(template, /create-ergonode-category-from-form/);
});

test('category form action requires login, retries throttling and refreshes virtual mapping data', () => {
    assert.match(component, /Ergonode_PublisherAdminUi\/js\/manual-auth/);
    assert.match(component, /Ergonode_PublisherAdminUi\/js\/json-post/);
    assert.match(component, /self\.auth\.ensure\(\)/);
    assert.doesNotMatch(component, /function createAuth/);
    assert.match(jsonPost, /timeout: 60000/);
    assert.match(component, /retry_after_seconds/);
    assert.match(component, /data\.ergonode_category_code/);
    assert.match(manualAuth, /Why is login required\?/);
    assert.match(manualAuth, /remember_me/);
    assert.match(component, /canCreate/);
});

test('category creation endpoint is POST-only and uses mapping-save ACL', () => {
    assert.match(controller, /implements HttpPostActionInterface/);
    assert.match(controller, /ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping_save'/);
    assert.match(controller, /CategoryFormPublisher/);
    assert.match(controller, /retry_after_seconds/);
});
