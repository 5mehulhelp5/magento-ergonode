'use strict';

const fs = require('node:fs');
const path = require('node:path');

let backendRoot = __dirname;
while (!fs.existsSync(path.join(backendRoot, 'app/code/Ergonode')) && path.dirname(backendRoot) !== backendRoot) {
    backendRoot = path.dirname(backendRoot);
}

function moduleRoot(name) {
    const local = path.join(backendRoot, 'app/code/Ergonode', name);
    if (fs.existsSync(local)) {
        return local;
    }

    const packageName = name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
    return path.join(backendRoot, 'vendor/ergonode', `module-${packageName}`);
}

module.exports = {backendRoot, moduleRoot};
