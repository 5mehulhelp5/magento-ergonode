'use strict';

const fs = require('node:fs');
const path = require('node:path');

let projectRoot = __dirname;
while (!fs.existsSync(path.join(projectRoot, 'app/etc/config.php'))) {
    const parent = path.dirname(projectRoot);
    if (parent === projectRoot) {
        throw new Error('Cannot locate the Magento project root.');
    }
    projectRoot = parent;
}

function modulePath(name, packageName) {
    const candidates = [
        path.join(projectRoot, 'app/code/Ergonode', name),
        path.join(projectRoot, 'packages/ergonode', packageName),
        path.join(projectRoot, 'vendor/ergonode', packageName)
    ];
    const root = candidates.find((candidate) => fs.existsSync(path.join(candidate, 'composer.json')));
    if (!root) {
        throw new Error(`Cannot locate Ergonode_${name}.`);
    }

    return root;
}

module.exports = {modulePath, projectRoot};
