'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const backendRoot = path.resolve(moduleRoot, '../../..');
const read = (file) => fs.readFileSync(path.join(moduleRoot, file), 'utf8');
const readBackend = (file) => fs.readFileSync(path.join(backendRoot, file), 'utf8');

test('category attribute and option views inherit shared bulk add-to-mapping behavior', () => {
    const attributeLayout = read('view/adminhtml/layout/ergonode_category_attribute_index.xml');
    const optionController = read('Controller/Adminhtml/Category/Option/Index.php');
    const attributeScript = readBackend(
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/attribute-mapping.js'
    );
    const optionScript = readBackend(
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/option-mapping.js'
    );

    assert.match(attributeLayout, /Ergonode_CoreAdminUi::attribute\/mapping\.phtml/);
    assert.match(optionController, /Ergonode_CoreAdminUi::option\/mapping\.phtml/);
    [attributeScript, optionScript].forEach((script) => {
        assert.match(script, /Ergonode_CoreAdminUi\/js\/source-bulk-transfer/);
        assert.match(script, /sourceBulkTransfer\.bind\(/);
    });
});

test('shared Storybook behavior matrix covers category attributes and options', () => {
    const story = readBackend('vendor/ergonode/module-core-admin-ui/Test/Storybook/MappingWorkspace.stories.js');
    const fixture = readBackend('dev/tools/ergonode-storybook/src/mapping-workspace.js');

    assert.match(story, /KontraktTransferuDlaWspolnychWidokow/);
    assert.match(story, /mappingViews\.forEach/);
    assert.match(story, /await userEvent\.click\(addToMapping\)/);
    assert.match(fixture, /mappingViews = \[[^\]]*'option'[^\]]*'category-attribute'/);
});
