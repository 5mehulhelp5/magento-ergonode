'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {backendRoot} = require('./module-paths.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(moduleRoot, file), 'utf8');
const readBackend = (file) => fs.readFileSync(path.join(backendRoot, file), 'utf8');

test('template sources integrate the shared bulk add-to-mapping behavior', () => {
    const template = read('view/adminhtml/templates/template/index.phtml');
    const script = read('view/adminhtml/web/js/template-admin.js');
    const bulkTransfer = readBackend(
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/source-bulk-transfer.js'
    );

    assert.equal((template.match(/data-source-panel=/g) || []).length, 2);
    assert.match(template, /data-role="template-drop-source"[\s\S]*?data-source-panel="ergo"/);
    assert.match(template, /data-role="attribute-set-drop-source"[\s\S]*?data-source-panel="magento"/);
    assert.match(script, /Ergonode_CoreAdminUi\/js\/source-bulk-transfer/);
    assert.match(script, /sourceBulkTransfer\.bind\(scope, element,/);
    assert.match(script, /return addDraftTemplate\([\s\S]*?, true\);/);
    assert.match(script, /return addDraftAttributeSet\([\s\S]*?, true\);/);
    assert.match(bulkTransfer, /\.veui-source-options \.veui-entity-options-menu/);
});

test('shared Storybook behavior matrix keeps the template view in the bulk-transfer contract', () => {
    const story = readBackend('vendor/ergonode/module-core-admin-ui/Test/Storybook/MappingWorkspace.stories.js');
    const fixture = readBackend('dev/tools/ergonode-storybook/src/mapping-workspace.js');

    assert.match(story, /KontraktTransferuDlaWspolnychWidokow/);
    assert.match(story, /mappingViews\.forEach/);
    assert.match(story, /\.veui-source-options \.veui-entity-options-menu/);
    assert.match(story, /await userEvent\.click\(addToMapping\)/);
    assert.match(fixture, /mappingViews = \[[^\]]*'template'/);
    assert.match(fixture, /if \(view !== 'template'\) \{\s*options\.dataset\.sourceOptions = side;/);
});
