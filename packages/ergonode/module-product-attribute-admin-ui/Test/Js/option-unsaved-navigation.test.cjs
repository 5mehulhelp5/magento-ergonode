'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const source = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi'), 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);

test('option mapping binds shared unsaved navigation to its dirty state and save request', () => {
    assert.match(source, /Ergonode_CoreAdminUi\/js\/unsaved-navigation/);
    assert.match(source, /unsavedNavigation\.bind\(scope, \{/);
    assert.match(source, /isDirty: function \(\) \{[\s\S]*?hasUnsavedChanges\(element\)/);
    assert.match(source, /save: function \(\) \{[\s\S]*?saveWithFeedback\(element, element\.veaConfig\)/);
});

test('option context switching uses the same save-discard-cancel guard', () => {
    const switcher = source.slice(
        source.indexOf('function initOptionContextSwitcher(root)'),
        source.indexOf('return function (config, element)')
    );

    assert.match(switcher, /root\.veaUnsavedNavigation\.request\(\{/);
    assert.match(switcher, /onCancel: function \(\) \{[\s\S]*?select\.value = currentValue/);
    assert.match(switcher, /onSaveError: function \(\) \{[\s\S]*?select\.value = currentValue/);
    assert.doesNotMatch(switcher, /window\.confirm\(/);
});
