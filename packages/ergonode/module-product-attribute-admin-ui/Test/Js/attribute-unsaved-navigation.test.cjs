'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const source = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi'), 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);

test('attribute mapping binds shared unsaved navigation to its dirty state and save request', () => {
    assert.match(source, /Ergonode_CoreAdminUi\/js\/unsaved-navigation/);
    assert.match(source, /unsavedNavigation\.bind\(scope, \{/);
    assert.match(source, /isDirty: function \(\) \{[\s\S]*?hasUnsavedChanges\(element\)/);
    assert.match(source, /save: function \(\) \{[\s\S]*?saveMappings\(element\)/);
    assert.match(source, /delete element\.veaUnsavedNavigation/);
});
