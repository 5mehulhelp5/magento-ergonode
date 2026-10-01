'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const expectedModuleName = 'Ergonode_AttributePublisherAdminUi';
const expectedNamespace = 'Ergonode\\AttributePublisherAdminUi';
const expectedPackage = 'ergonode/module-attribute-publisher-admin-ui';

const composer = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'composer.json'), 'utf8'));
const registration = fs.readFileSync(path.join(moduleRoot, 'registration.php'), 'utf8');
const moduleXml = fs.readFileSync(path.join(moduleRoot, 'etc/module.xml'), 'utf8');

test('module metadata exposes exactly the Ergonode attribute publisher identity', () => {
    assert.equal(path.basename(moduleRoot), expectedPackage.split('/')[1]);
    assert.equal(composer.name, expectedPackage);
    assert.equal(composer.autoload['psr-4'][`${expectedNamespace}\\`], '');
    assert.equal(composer.extra.ergonode.lifecycle, 'development');
    assert.match(registration, new RegExp(`'${expectedModuleName}'`));
    assert.match(moduleXml, new RegExp(`<module name="${expectedModuleName}">`));
});

test('module does not expose schema or package compatibility shims', () => {
    assert.equal(composer.replace, undefined);
    assert.equal(composer.provide, undefined);
    assert.equal(fs.existsSync(path.join(moduleRoot, 'Setup')), false);
    assert.equal(fs.existsSync(path.join(moduleRoot, 'etc/db_schema.xml')), false);
});
