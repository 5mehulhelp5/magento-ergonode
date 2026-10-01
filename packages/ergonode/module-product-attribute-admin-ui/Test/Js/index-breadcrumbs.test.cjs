'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');

[
    'Controller/Adminhtml/Attribute/Index.php',
    'Controller/Adminhtml/Option/Index.php'
].forEach((controllerPath) => {
    test(`${controllerPath} adds the Ergonode breadcrumb once`, () => {
        const controller = fs.readFileSync(path.join(moduleRoot, controllerPath), 'utf8');
        const breadcrumbs = controller.match(/addBreadcrumb\(__\('Ergonode'\), __\('Ergonode'\)\)/g) || [];

        assert.equal(breadcrumbs.length, 1);
    });
});
