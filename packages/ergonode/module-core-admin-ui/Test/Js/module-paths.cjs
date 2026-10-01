'use strict';

const fs = require('node:fs');
const path = require('node:path');

let projectRoot = path.resolve(__dirname, '../..');
while (!fs.existsSync(path.join(projectRoot, 'app/etc/config.php'))) {
    const parent = path.dirname(projectRoot);
    if (parent === projectRoot) {
        throw new Error('Cannot locate the Magento project root.');
    }
    projectRoot = parent;
}

function modulePath(name) {
    const slug = name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
    const candidates = [
        path.join(projectRoot, 'app/code/Ergonode', name),
        path.join(projectRoot, 'packages/ergonode', `module-${slug}`),
        path.join(projectRoot, 'packages/backlog/ergonode', `module-${slug}`),
    ];

    return candidates.find((candidate) => fs.existsSync(candidate)) || candidates[0];
}

function moduleDirectories() {
    const roots = [
        path.join(projectRoot, 'app/code/Ergonode'),
        path.join(projectRoot, 'packages/ergonode'),
    ];

    return roots.flatMap((root) => fs.existsSync(root)
        ? fs.readdirSync(root, {withFileTypes: true})
            .filter((entry) => entry.isDirectory())
            .map((entry) => path.join(root, entry.name))
        : []);
}

module.exports = {projectRoot, modulePath, moduleDirectories};
