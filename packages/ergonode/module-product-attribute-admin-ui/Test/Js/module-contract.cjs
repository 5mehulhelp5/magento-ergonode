'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

let repositoryRoot = __dirname;
while (!fs.existsSync(path.join(repositoryRoot, 'dev/tools/ergonode-storybook/module-catalog.js'))) {
    const parent = path.dirname(repositoryRoot);
    assert.notEqual(parent, repositoryRoot, 'repository root must contain the Ergonode module catalog');
    repositoryRoot = parent;
}
const {discoverModules} = require(path.join(repositoryRoot, 'dev/tools/ergonode-storybook/module-catalog.js'));
const modules = new Map(discoverModules(repositoryRoot).map((module) => [module.name, module.directory]));

function resolveModuleRoot(name) {
    assert.ok(modules.has(name), `Magento module ${name} must exist in the repository`);

    return modules.get(name);
}

module.exports = {repositoryRoot, resolveModuleRoot};
