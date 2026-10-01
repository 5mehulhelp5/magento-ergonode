'use strict';

const fs = require('node:fs');
const path = require('node:path');

let projectRoot = __dirname;
while (!fs.existsSync(path.join(projectRoot, 'app/etc/config.php'))) {
    const parent = path.dirname(projectRoot);
    if (parent === projectRoot) {
        throw new Error('Cannot locate the Magento project root');
    }
    projectRoot = parent;
}

function moduleRoot(name, packageName) {
    const packageRoot = path.join(projectRoot, 'packages/ergonode', packageName);
    const appCodeRoot = path.join(projectRoot, 'app/code/Ergonode', name);
    const root = fs.existsSync(path.join(packageRoot, 'composer.json')) ? packageRoot : appCodeRoot;
    if (!fs.existsSync(path.join(root, 'composer.json'))) {
        throw new Error(`Cannot locate Ergonode_${name}`);
    }
    return root;
}

module.exports = {projectRoot, moduleRoot};
